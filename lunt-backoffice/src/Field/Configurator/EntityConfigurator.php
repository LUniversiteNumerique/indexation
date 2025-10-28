<?php

namespace App\Field\Configurator;

use App\Controller\NoticeCrudController;
use App\Field\EntityField;
use Doctrine\ORM\{EntityRepository, PersistentCollection};
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Config\{Action, Crud, Option\EA, Option\TextAlign};
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldConfiguratorInterface;
use EasyCorp\Bundle\EasyAdminBundle\Dto\{EntityDto, FieldDto};
use EasyCorp\Bundle\EasyAdminBundle\Factory\{ControllerFactory, EntityFactory};
use EasyCorp\Bundle\EasyAdminBundle\Form\Type\{CrudAutocompleteType, CrudFormType};
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use RuntimeException;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\PropertyAccess\{Exception\UnexpectedTypeException, PropertyAccessor};
use Traversable;
use function Symfony\Component\{Translation\t, String\u};

/**
 * Configure les champs d'entité pour EasyAdmin.
 */
readonly class EntityConfigurator implements FieldConfiguratorInterface
{
    /**
     * @param EntityFactory $entityFactory
     * @param RequestStack $requestStack
     * @param ControllerFactory $controllerFactory
     * @param AdminUrlGenerator $adminUrlGenerator
     */
    public function __construct(
        private EntityFactory     $entityFactory,
        private RequestStack      $requestStack,
        private ControllerFactory $controllerFactory,
        private AdminUrlGenerator $adminUrlGenerator
    ) {}

    /**
     * Vérifie si le configurateur supporte le champ donné.
     *
     * @param FieldDto $field
     * @param EntityDto $entityDto
     * @return bool
     */
    public function supports(FieldDto $field, EntityDto $entityDto): bool
    {
        return EntityField::class === $field->getFieldFqcn();
    }

    /**
     * Configure le champ d'entité selon le contexte et l'entité.
     *
     * @param FieldDto $field
     * @param EntityDto $entityDto
     * @param AdminContext $context
     * @return void
     */
    public function configure(FieldDto $field, EntityDto $entityDto, AdminContext $context): void
    {
        $propertyName = $field->getProperty();
        $mapped = $field->getFormTypeOption('mapped');

        if (!$entityDto->isAssociation($propertyName) && $mapped) {
            throw new RuntimeException(sprintf(
                'The "%s" field is not a Doctrine association, so it cannot be used as an association field.', $propertyName
            ));
        }

        $targetEntityFqcn = $mapped === false
            ? $field->getFormTypeOption('class')
            : $field->getDoctrineMetadata()->get('targetEntity');

        $targetCrudControllerFqcn = $field->getCustomOption(EntityField::OPTION_EMBEDDED_CRUD_FORM_CONTROLLER)
            ?? $context->getCrudControllers()->findCrudFqcnByEntityFqcn($targetEntityFqcn);

        if ($field->getCustomOption(EntityField::OPTION_RENDER_AS_EMBEDDED_FORM)) {
            $this->assertToOneAssociation($entityDto, $propertyName, $field, $context, $targetEntityFqcn, $targetCrudControllerFqcn);
            $this->configureCrudForm($field, $entityDto, $propertyName, $targetEntityFqcn, $targetCrudControllerFqcn);
            return;
        }

        $field->setCustomOption(EntityField::OPTION_EMBEDDED_CRUD_FORM_CONTROLLER, $targetCrudControllerFqcn);
        $this->configureFormOptions($field);

        // check for embedded associations
        $propertyNameParts = explode('.', $propertyName);
        if (count($propertyNameParts) > 1) {
            $this->configureEmbeddedAssociation($field, $entityDto, $propertyNameParts, $targetCrudControllerFqcn);
        } else {
            $isIndexAction = Action::INDEX === $context->getCrud()->getCurrentAction();
            if ($mapped === false || $entityDto->isToOneAssociation($propertyName)) {
                $field->setFormattedValue($this->configureToOneAssociation($field, $isIndexAction));
            } elseif ($entityDto->isToManyAssociation($propertyName)) {
                $field->setFormattedValue($this->configureToManyAssociation($field, $isIndexAction));
            }
        }

        if ($field->getCustomOption(EntityField::OPTION_AUTOCOMPLETE)) {
            $this->configureAutocomplete($field, $context, $propertyName);
        } else {
            $this->configureQueryBuilder($field);
        }
    }

    /**
     * Configure les options du formulaire pour le champ.
     *
     * @param FieldDto $field
     * @return void
     */
    private function configureFormOptions(FieldDto $field): void
    {
        if (EntityField::WIDGET_AUTOCOMPLETE === $field->getCustomOption(EntityField::OPTION_WIDGET)) {
            $field->setFormTypeOption('attr.data-ea-widget', 'ea-autocomplete');
        }
        if ($field->getCustomOption('allow-item-create')) {
            $field->setFormTypeOption('attr.data-ea-autocomplete-allow-item-create', true);
        }
        if ($preload = $field->getCustomOption('preload')) {
            $field->setFormTypeOption('attr.data-ea-autocomplete-preload', $preload);
        }
    }

    /**
     * Configure une association imbriquée.
     *
     * @param FieldDto $field
     * @param EntityDto $entityDto
     * @param array $propertyNameParts
     * @param string|null $targetCrudControllerFqcn
     * @return void
     */
    private function configureEmbeddedAssociation(FieldDto $field, EntityDto $entityDto, array $propertyNameParts, ?string $targetCrudControllerFqcn): void
    {
        // prepare starting class for association
        $targetEntityFqcn = $entityDto->getPropertyMetadata($propertyNameParts[0])->get('targetEntity');
        array_shift($propertyNameParts);
        $metadata = $this->entityFactory->getEntityMetadata($targetEntityFqcn);

        foreach ($propertyNameParts as $association) {
            if (!$metadata->hasAssociation($association)) {
                throw new RuntimeException(sprintf(
                    'There is no association for the class "%s" with name "%s"', $targetEntityFqcn, $association
                ));
            }
            // overwrite next class from association
            $targetEntityFqcn = $metadata->getAssociationTargetClass($association);
            // read next association metadata
            $metadata = $this->entityFactory->getEntityMetadata($targetEntityFqcn);
        }

        $accessor = new PropertyAccessor();
        $field->setFormTypeOptionIfNotSet('class', $targetEntityFqcn);

        try {
            $relatedEntityId = $accessor->getValue(
                $entityDto->getInstance(),
                implode('.', $propertyNameParts) . '.' . $metadata->getIdentifierFieldNames()[0]
            );
            $relatedEntityDto = $this->entityFactory->create($targetEntityFqcn, $relatedEntityId);

            $field->setCustomOption(EntityField::OPTION_RELATED_URL, $this->generateLinkToAssociatedEntity($targetCrudControllerFqcn, $relatedEntityDto));
            $field->setFormattedValue($this->formatAsString($relatedEntityDto->getInstance(), $relatedEntityDto));
        } catch (UnexpectedTypeException) {}
    }

    /**
     * Configure l'autocomplétion pour le champ.
     *
     * @param FieldDto $field
     * @param AdminContext $context
     * @param string $propertyName
     * @return void
     */
    private function configureAutocomplete(FieldDto $field, AdminContext $context, string $propertyName): void
    {
        $targetCrudControllerFqcn = $field->getCustomOption(EntityField::OPTION_EMBEDDED_CRUD_FORM_CONTROLLER);
        if (null === $targetCrudControllerFqcn) {
            throw new RuntimeException(sprintf(
                'The "%s" field cannot be autocompleted because it doesn\'t define the related CRUD controller FQCN with the "setCrudController()" method.', $field->getProperty()
            ));
        }

        $field->setFormType(CrudAutocompleteType::class);
        $autocompleteEndpointUrl = $this->adminUrlGenerator
            ->unsetAll()
            ->set('page', 1)
            ->setController($targetCrudControllerFqcn)
            ->setAction('autocomplete')
            ->set(EntityField::PARAM_AUTOCOMPLETE_CONTEXT, [
                EA::CRUD_CONTROLLER_FQCN => $context->getRequest()->query->get(EA::CRUD_CONTROLLER_FQCN),
                'propertyName' => $propertyName,
                'originatingPage' => $context->getCrud()->getCurrentPage(),
            ])
            ->generateUrl();

        $field->setFormTypeOption('attr.data-ea-autocomplete-endpoint-url', $autocompleteEndpointUrl);
    }

    /**
     * Configure le query builder pour le champ.
     *
     * @param FieldDto $field
     * @return void
     */
    private function configureQueryBuilder(FieldDto $field): void
    {
        $field->setFormTypeOptionIfNotSet('query_builder', static function (EntityRepository $repository) use ($field) {
            $queryBuilder = $repository->createQueryBuilder('entity');
            if ($queryBuilderCallable = $field->getCustomOption(EntityField::OPTION_QUERY_BUILDER_CALLABLE)) {
                $queryBuilderCallable($queryBuilder);
            }
            return $queryBuilder;
        });
    }

    /**
     * Vérifie que l'association est de type to-one.
     *
     * @param EntityDto $entityDto
     * @param string $propertyName
     * @param FieldDto $field
     * @param AdminContext $context
     * @param string $targetEntityFqcn
     * @param string|null $targetCrudControllerFqcn
     * @return void
     */
    private function assertToOneAssociation(EntityDto $entityDto, string $propertyName, FieldDto $field, AdminContext $context, string $targetEntityFqcn, ?string $targetCrudControllerFqcn): void
    {
        if (!$entityDto->isToOneAssociation($propertyName)) {
            throw new RuntimeException(sprintf(
                'The "%s" association field of "%s" is a to-many association but it\'s trying to use the "renderAsEmbeddedForm()" option, which is only available for to-one associations. If you want to use a CRUD form to render to-many associations, use a CollectionField instead of the EntityField.',
                $field->getProperty(),
                $context->getCrud()?->getControllerFqcn()
            ));
        }
        if (null === $targetCrudControllerFqcn) {
            throw new RuntimeException(sprintf(
                'The "%s" association field of "%s" wants to render its contents using an EasyAdmin CRUD form. However, no CRUD form was found related to this field. You can either create a CRUD controller for the entity "%s" or pass the CRUD controller to use as the first argument of the "renderAsEmbeddedForm()" method.',
                $field->getProperty(),
                $context->getCrud()?->getControllerFqcn(),
                $targetEntityFqcn
            ));
        }
    }

    /**
     * Configure l'affichage d'une association to-one.
     *
     * @param FieldDto $field
     * @param bool $isIndex
     * @return string|null
     */
    private function configureToOneAssociation(FieldDto $field, bool $isIndex): ?string
    {
        $mapped = $field->getFormTypeOption('mapped');
        $field->setCustomOption(EntityField::OPTION_DOCTRINE_ASSOCIATION_TYPE, 'toOne');

        if (false === $field->getFormTypeOption('required')) {
            $field->setFormTypeOptionIfNotSet('attr.placeholder', t('label.form.empty_value', [], 'EasyAdminBundle'));
        }

        $targetEntityFqcn = $mapped===false ?
            $field->getFormTypeOption('class'):
            $field->getDoctrineMetadata()->get('targetEntity');

        $targetCrudControllerFqcn = $field->getCustomOption(EntityField::OPTION_EMBEDDED_CRUD_FORM_CONTROLLER);


        $targetEntityDto = null === $field->getValue()
            ? $this->entityFactory->create($targetEntityFqcn)
            : $this->entityFactory->createForEntityInstance($field->getValue());
        $field->setFormTypeOptionIfNotSet('class', $targetEntityDto->getFqcn());

        $field->setCustomOption(EntityField::OPTION_RELATED_URL, $this->generateLinkToAssociatedEntity($targetCrudControllerFqcn, $targetEntityDto));

        $toto = $this->formatAsString($field->getValue(), $targetEntityDto);

        return $isIndex ? u($toto)->truncate(32, '…')->toString() : $toto;
    }

    /**
     * Configure l'affichage d'une association to-many.
     *
     * @param FieldDto $field
     * @param bool $isIndex
     * @return string|array|int
     */
    private function configureToManyAssociation(FieldDto $field, bool $isIndex): string|array|int
    {
        $field->setCustomOption(EntityField::OPTION_DOCTRINE_ASSOCIATION_TYPE, 'toMany');

        $field->setFormTypeOptionIfNotSet('multiple', true);

        /* @var PersistentCollection $collection */
        $field->setFormTypeOptionIfNotSet('class', $field->getDoctrineMetadata()->get('targetEntity'));

        if (null === $field->getTextAlign()) $field->setTextAlign(TextAlign::RIGHT);
        $targetCrudControllerFqcn = $field->getCustomOption(EntityField::OPTION_EMBEDDED_CRUD_FORM_CONTROLLER);

        $collectionItemsAsText = []; $resource = $targetCrudControllerFqcn === NoticeCrudController::class;
        foreach ($field->getValue() ?? [] as $item) {
            if (!is_string($item) && !(is_object($item) && method_exists($item, '__toString')))
                return $this->countNumElements($field->getValue());
            if($resource) {
                $targetEntityDto = $this->entityFactory->createForEntityInstance($item);
                $collectionItemsAsText[$this->generateLinkToAssociatedEntity($targetCrudControllerFqcn, $targetEntityDto)] = u($this->formatAsString($item, $targetEntityDto))->truncate(100,'..')->toString();
            } else $collectionItemsAsText[] = (string) $item;
        }

        return $resource ? $collectionItemsAsText : u(', ')->join($collectionItemsAsText)->truncate($isIndex ? 32 : 512, '…')->toString();
    }

    /**
     * Formate une entité en chaîne de caractères.
     *
     * @param mixed $entityInstance
     * @param EntityDto $entityDto
     * @return string|null
     */
    private function formatAsString(mixed $entityInstance, EntityDto $entityDto): ?string
    {
        if (null === $entityInstance) return null;

        if (method_exists($entityInstance, '__toString'))
            return (string) $entityInstance;

        if (null !== $primaryKeyValue = $entityDto->getPrimaryKeyValue())
            return sprintf('%s #%s', $entityDto->getName(), $primaryKeyValue);

        return $entityDto->getName();
    }

    /**
     * Génère un lien vers une entité associée.
     *
     * @param string|null $crudController
     * @param EntityDto $entityDto
     * @return string|null
     */
    private function generateLinkToAssociatedEntity(?string $crudController, EntityDto $entityDto): ?string
    {
        if (null === $crudController) return null;

        return $this->adminUrlGenerator
            ->setController($crudController)
            ->setAction(Action::DETAIL)
            ->setEntityId($entityDto->getPrimaryKeyValue())
            ->unset(EA::MENU_INDEX)
            ->unset(EA::SUBMENU_INDEX)
            ->generateUrl();
    }

    /**
     * Compte le nombre d'éléments dans une collection.
     *
     * @param mixed $collection
     * @return int
     */
    private function countNumElements(mixed $collection): int
    {
        if (null === $collection) return 0;

        if (is_countable($collection)) return count($collection);

        if ($collection instanceof Traversable) return iterator_count($collection);

        return 0;
    }

    /**
     * Configure le formulaire CRUD pour une association.
     *
     * @param FieldDto $field
     * @param EntityDto $entityDto
     * @param string $propertyName
     * @param string $targetEntityFqcn
     * @param string $targetCrudControllerFqcn
     * @return void
     */
    private function configureCrudForm(FieldDto $field, EntityDto $entityDto, string $propertyName, string $targetEntityFqcn, string $targetCrudControllerFqcn): void
    {
        $field->setFormType(CrudFormType::class);
        $propertyAccessor = new PropertyAccessor();

        $associatedEntity = (null !== $entityDto->getInstance() && $propertyAccessor->isReadable($entityDto->getInstance(), $propertyName)) ?
            $propertyAccessor->getValue($entityDto->getInstance(), $propertyName) : null;

        if ($associatedEntity) {
            $targetCrudControllerAction = Action::EDIT;
            $targetCrudControllerPageName = $field->getCustomOption(EntityField::OPTION_EMBEDDED_CRUD_FORM_EDIT_PAGE_NAME) ?? Crud::PAGE_EDIT;
        } else {
            $targetCrudControllerAction = Action::NEW;
            $targetCrudControllerPageName = $field->getCustomOption(EntityField::OPTION_EMBEDDED_CRUD_FORM_NEW_PAGE_NAME) ?? Crud::PAGE_NEW;
        }

        $field->setFormTypeOption('entityDto',
            $this->createEntityDto($targetEntityFqcn, $targetCrudControllerFqcn, $targetCrudControllerAction, $targetCrudControllerPageName),
        );
    }

    /**
     * Crée un EntityDto pour une entité cible.
     *
     * @param string $entityFqcn
     * @param string $crudControllerFqcn
     * @param string $crudControllerAction
     * @param string $crudControllerPageName
     * @return EntityDto
     */
    private function createEntityDto(string $entityFqcn, string $crudControllerFqcn, string $crudControllerAction, string $crudControllerPageName): EntityDto
    {
        $entityDto = $this->entityFactory->create($entityFqcn);

        $crudController = $this->controllerFactory->getCrudControllerInstance(
            $crudControllerFqcn,
            $crudControllerAction,
            $this->requestStack->getMainRequest()
        );

        $fields = $crudController->configureFields($crudControllerPageName);

        $this->entityFactory->processFields($entityDto, FieldCollection::new($fields));

        return $entityDto;
    }
}