<?php

namespace App\Field\Configurator;

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
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\PropertyAccess\{Exception\UnexpectedTypeException, PropertyAccessor};
use function Symfony\Component\Translation\t;

readonly class EntityConfigurator implements FieldConfiguratorInterface
{
    public function __construct(
        private EntityFactory     $entityFactory,
        private RequestStack      $requestStack,
        private ControllerFactory $controllerFactory,
        private AdminUrlGenerator $adminUrlGenerator){}

    public function supports(FieldDto $field, EntityDto $entityDto): bool
    {
        return EntityField::class === $field->getFieldFqcn();
    }

    public function configure(FieldDto $field, EntityDto $entityDto, AdminContext $context): void
    {
        $propertyName = $field->getProperty();
        $mapped = $field->getFormTypeOption('mapped');
        if (!$entityDto->isAssociation($propertyName) && $mapped)
            throw new \RuntimeException(sprintf('The "%s" field is not a Doctrine association, so it cannot be used as an association field.', $propertyName));

        $targetEntityFqcn = $mapped===false ?
            $field->getFormTypeOption('class'):
            $field->getDoctrineMetadata()->get('targetEntity');

        // the target CRUD controller can be NULL; in that case, field value doesn't link to the related entity
        $targetCrudControllerFqcn = $field->getCustomOption(EntityField::OPTION_EMBEDDED_CRUD_FORM_CONTROLLER)
            ?? $context->getCrudControllers()->findCrudFqcnByEntityFqcn($targetEntityFqcn);

        if (true === $field->getCustomOption(EntityField::OPTION_RENDER_AS_EMBEDDED_FORM)) {
            if (false === $entityDto->isToOneAssociation($propertyName)) {
                throw new \RuntimeException(
                    sprintf(
                        'The "%s" association field of "%s" is a to-many association but it\'s trying to use the "renderAsEmbeddedForm()" option, which is only available for to-one associations. If you want to use a CRUD form to render to-many associations, use a CollectionField instead of the EntityField.',
                        $field->getProperty(),
                        $context->getCrud()?->getControllerFqcn(),
                    )
                );
            }

            if (null === $targetCrudControllerFqcn) {
                throw new \RuntimeException(
                    sprintf(
                        'The "%s" association field of "%s" wants to render its contents using an EasyAdmin CRUD form. However, no CRUD form was found related to this field. You can either create a CRUD controller for the entity "%s" or pass the CRUD controller to use as the first argument of the "renderAsEmbeddedForm()" method.',
                        $field->getProperty(),
                        $context->getCrud()?->getControllerFqcn(),
                        $targetEntityFqcn
                    )
                );
            }

            $this->configureCrudForm($field, $entityDto, $propertyName, $targetEntityFqcn, $targetCrudControllerFqcn);

            return;
        }

        $field->setCustomOption(EntityField::OPTION_EMBEDDED_CRUD_FORM_CONTROLLER, $targetCrudControllerFqcn);

        if (EntityField::WIDGET_AUTOCOMPLETE === $field->getCustomOption(EntityField::OPTION_WIDGET))
            $field->setFormTypeOption('attr.data-ea-widget', 'ea-autocomplete');
        if ($field->getCustomOption('allow-item-create'))
            $field->setFormTypeOption('attr.data-ea-autocomplete-allow-item-create',true);
        if ($preload = $field->getCustomOption('preload')) $field->setFormTypeOption('attr.data-ea-autocomplete-preload',$preload);

        // check for embedded associations
        $propertyNameParts = explode('.', $propertyName);
        if (\count($propertyNameParts) > 1) {
            // prepare starting class for association
            $targetEntityFqcn = $entityDto->getPropertyMetadata($propertyNameParts[0])->get('targetEntity');
            array_shift($propertyNameParts);
            $metadata = $this->entityFactory->getEntityMetadata($targetEntityFqcn);

            foreach ($propertyNameParts as $association) {
                if (!$metadata->hasAssociation($association))
                    throw new \RuntimeException(sprintf('There is no association for the class "%s" with name "%s"', $targetEntityFqcn, $association));

                // overwrite next class from association
                $targetEntityFqcn = $metadata->getAssociationTargetClass($association);

                // read next association metadata
                $metadata = $this->entityFactory->getEntityMetadata($targetEntityFqcn);
            }

            $accessor = new PropertyAccessor();
            $targetCrudControllerFqcn = $field->getCustomOption(EntityField::OPTION_EMBEDDED_CRUD_FORM_CONTROLLER);

            $field->setFormTypeOptionIfNotSet('class', $targetEntityFqcn);

            try {
                $relatedEntityId = $accessor->getValue($entityDto->getInstance(), $propertyName.'.'.$metadata->getIdentifierFieldNames()[0]);
                $relatedEntityDto = $this->entityFactory->create($targetEntityFqcn, $relatedEntityId);

                $field->setCustomOption(EntityField::OPTION_RELATED_URL, $this->generateLinkToAssociatedEntity($targetCrudControllerFqcn, $relatedEntityDto));
                $field->setFormattedValue($this->formatAsString($relatedEntityDto->getInstance(), $relatedEntityDto));
            } catch (UnexpectedTypeException) {}
        } else {

            if ($mapped === false || $entityDto->isToOneAssociation($propertyName))
                $this->configureToOneAssociation($field);
            elseif ($entityDto->isToManyAssociation($propertyName))
                $this->configureToManyAssociation($field);

        }

        if (true === $field->getCustomOption(EntityField::OPTION_AUTOCOMPLETE)) {
            $targetCrudControllerFqcn = $field->getCustomOption(EntityField::OPTION_EMBEDDED_CRUD_FORM_CONTROLLER);
            if (null === $targetCrudControllerFqcn)
                throw new \RuntimeException(sprintf('The "%s" field cannot be autocompleted because it doesn\'t define the related CRUD controller FQCN with the "setCrudController()" method.', $field->getProperty()));

            $field->setFormType(CrudAutocompleteType::class);
            $autocompleteEndpointUrl = $this->adminUrlGenerator
                ->unsetAll()
                ->set('page', 1) // The autocomplete should always start on the first page
                ->setController($field->getCustomOption(EntityField::OPTION_EMBEDDED_CRUD_FORM_CONTROLLER))
                ->setAction('autocomplete')
                ->set(EntityField::PARAM_AUTOCOMPLETE_CONTEXT, [
                    EA::CRUD_CONTROLLER_FQCN => $context->getRequest()->query->get(EA::CRUD_CONTROLLER_FQCN),
                    'propertyName' => $propertyName,
                    'originatingPage' => $context->getCrud()->getCurrentPage(),
                ])
                ->generateUrl();

            $field->setFormTypeOption('attr.data-ea-autocomplete-endpoint-url', $autocompleteEndpointUrl);
        } else $field->setFormTypeOptionIfNotSet('query_builder', static function (EntityRepository $repository) use ($field) {
            $queryBuilder = $repository->createQueryBuilder('entity');
            if (null !== $queryBuilderCallable = $field->getCustomOption(EntityField::OPTION_QUERY_BUILDER_CALLABLE))
                $queryBuilderCallable($queryBuilder);
            return $queryBuilder;
        });

    }

    private function configureToOneAssociation(FieldDto $field): void
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

        $field->setFormattedValue($this->formatAsString($field->getValue(), $targetEntityDto));
    }

    private function configureToManyAssociation(FieldDto $field): void
    {
        $field->setCustomOption(EntityField::OPTION_DOCTRINE_ASSOCIATION_TYPE, 'toMany');

        $field->setFormTypeOptionIfNotSet('multiple', true);

        /* @var PersistentCollection $collection */
        $field->setFormTypeOptionIfNotSet('class', $field->getDoctrineMetadata()->get('targetEntity'));

        if (null === $field->getTextAlign()) {
            $field->setTextAlign(TextAlign::RIGHT);
        }

        $field->setFormattedValue($this->countNumElements($field->getValue()));
    }

    private function formatAsString($entityInstance, EntityDto $entityDto): ?string
    {
        if (null === $entityInstance) return null;

        if (method_exists($entityInstance, '__toString'))
            return (string) $entityInstance;

        if (null !== $primaryKeyValue = $entityDto->getPrimaryKeyValue())
            return sprintf('%s #%s', $entityDto->getName(), $primaryKeyValue);

        return $entityDto->getName();
    }

    private function generateLinkToAssociatedEntity(?string $crudController, EntityDto $entityDto): ?string
    {
        if (null === $crudController) return null;

        return $this->adminUrlGenerator
            ->setController($crudController)
            ->setAction(Action::DETAIL)
            ->setEntityId($entityDto->getPrimaryKeyValue())
            ->unset(EA::MENU_INDEX)
            ->unset(EA::SUBMENU_INDEX)
            ->includeReferrer()
            ->generateUrl();
    }

    private function countNumElements($collection): int
    {
        if (null === $collection) return 0;

        if (is_countable($collection)) return \count($collection);

        if ($collection instanceof \Traversable) return iterator_count($collection);

        return 0;
    }

    private function configureCrudForm(FieldDto $field, EntityDto $entityDto, string $propertyName, string $targetEntityFqcn, string $targetCrudControllerFqcn): void
    {
        $field->setFormType(CrudFormType::class);
        $propertyAccessor = new PropertyAccessor();

        $associatedEntity = (null !== $entityDto->getInstance() && $propertyAccessor->isReadable($entityDto->getInstance(), $propertyName)) ?
            $propertyAccessor->getValue($entityDto->getInstance(), $propertyName) : null;

        if (null === $associatedEntity) {
            $targetCrudControllerAction = Action::NEW;
            $targetCrudControllerPageName = $field->getCustomOption(EntityField::OPTION_EMBEDDED_CRUD_FORM_NEW_PAGE_NAME) ?? Crud::PAGE_NEW;
        } else {
            $targetCrudControllerAction = Action::EDIT;
            $targetCrudControllerPageName = $field->getCustomOption(EntityField::OPTION_EMBEDDED_CRUD_FORM_EDIT_PAGE_NAME) ?? Crud::PAGE_EDIT;
        }

        $field->setFormTypeOption('entityDto',
            $this->createEntityDto($targetEntityFqcn, $targetCrudControllerFqcn, $targetCrudControllerAction, $targetCrudControllerPageName),
        );
    }

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