<?php

namespace App\Controller;

use App\Entity\Dossier;
use App\Field\EntityField;
use App\Form\DossierType;
use App\Repository\DossierRepository;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use EasyCorp\Bundle\EasyAdminBundle\Collection\{FieldCollection,FilterCollection};
use EasyCorp\Bundle\EasyAdminBundle\Config\{Action, Actions, Crud, KeyValueStore, Option\EA};
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Dto\{EntityDto,SearchDto};
use EasyCorp\Bundle\EasyAdminBundle\Field\{IdField,AssociationField,TextField};
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\HttpFoundation\{RedirectResponse,Response};

/**
 * Contrôleur CRUD pour l'entité Dossier.
 */
class DossierCrudController extends AbstractCrudController
{
    /**
     * @param DossierRepository $repository
     * @param AdminUrlGenerator $generator
     */
    public function __construct(
        private readonly DossierRepository $repository,
        private readonly AdminUrlGenerator $generator,
    ) {}

    /**
     * Retourne le FQCN de l'entité gérée.
     *
     * @return string
     */
    public static function getEntityFqcn(): string
    {
        return Dossier::class;
    }

    /**
     * Configure le CRUD.
     *
     * @param Crud $crud
     * @return Crud
     */
    public function configureCrud(Crud $crud): Crud
    {
        return parent::configureCrud($crud)
            ->showEntityActionsInlined(false)
            ->setEntityLabelInPlural('Répertoires')
            ->setEntityLabelInSingular('Répertoire')
            ->overrideTemplates(['crud/detail' => 'admin/actions/dossier.html.twig']);
    }

    /**
     * Configure les actions disponibles.
     *
     * @param Actions $actions
     * @return Actions
     */
    public function configureActions(Actions $actions): Actions
    {
        return parent::configureActions($actions)
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->add(Crud::PAGE_DETAIL, Action::NEW)
            ->add(Crud::PAGE_NEW, Action::INDEX)
            ->update(
                Crud::PAGE_DETAIL,
                Action::DELETE,
                static fn(Action $a) => $a->displayIf(
                    static fn(Dossier $d) => $d->getChildren()->isEmpty() && $d->getNotices()->isEmpty()
                )
            )
            ->remove(Crud::PAGE_DETAIL, Action::EDIT)
            ->remove(Crud::PAGE_DETAIL, Action::INDEX)
            ->setPermission(Action::INDEX, 'ROLE_READ_DOSS')
            ->setPermission(Action::DETAIL, 'ROLE_READ_DOSS')
            ->setPermission(Action::NEW, 'ROLE_CREA_DOSS')
            ->setPermission(Action::EDIT, 'ROLE_EDIT_DOSS')
            ->setPermission(Action::DELETE, 'ROLE_DROP_DOSS');
    }

    /**
     * Configure les champs affichés selon la page.
     *
     * @param string $pageName
     * @return iterable
     */
    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->onlyOnDetail(),
            TextField::new('nom'),
            EntityField::new('children')->onlyOnDetail(),
            AssociationField::new('notices')->onlyOnDetail(),
        ];
    }

    /**
     * Personnalise la requête d'index pour n'afficher que les dossiers racines.
     *
     * @param SearchDto $searchDto
     * @param EntityDto $entityDto
     * @param FieldCollection $fields
     * @param FilterCollection $filters
     * @return QueryBuilder
     */
    public function createIndexQueryBuilder(
        SearchDto $searchDto,
        EntityDto $entityDto,
        FieldCollection $fields,
        FilterCollection $filters
    ): QueryBuilder {
        return parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters)
            ->andWhere('entity.parent is null');
    }

    /**
     * Crée le form builder pour la création d'un dossier.
     *
     * @param EntityDto $entityDto
     * @param KeyValueStore $formOptions
     * @param AdminContext $context
     * @return FormBuilderInterface
     */
    public function createNewFormBuilder(
        EntityDto $entityDto,
        KeyValueStore $formOptions,
        AdminContext $context
    ): FormBuilderInterface {
        /** @var Dossier $ent */
        $ent = $entityDto->getInstance()->setUser($this->getUser());
        if ($id = $context->getRequest()->get('folderId')) {
            $entityDto->setInstance($ent->setParent($this->repository->find($id)));
        }
        return parent::createNewFormBuilder($entityDto, $formOptions, $context);
    }

    /**
     * Affiche la page d'index après vérification des droits.
     *
     * @param AdminContext $context
     * @return Response
     */
    public function index(AdminContext $context)
    {
        $this->denyAccessUnlessGranted('ROLE_READ_DOSS');
        return parent::index($context);
    }

    /**
     * Affiche la page de création après vérification des droits.
     *
     * @param AdminContext $context
     * @return Response
     */
    public function new(AdminContext $context)
    {
        $this->denyAccessUnlessGranted('ROLE_CREA_DOSS');
        return parent::new($context);
    }

    /**
     * Affiche le détail d'un dossier après vérification des droits.
     *
     * @param AdminContext $context
     * @return KeyValueStore|Response
     */
    public function detail(AdminContext $context): KeyValueStore|Response
    {
        $this->denyAccessUnlessGranted('ROLE_READ_DOSS');
        $resParams = parent::detail($context);
        $resParams->set('form', $this->createForm(DossierType::class));
        return $resParams;
    }

    /**
     * Affiche la page d'édition après vérification des droits.
     *
     * @param AdminContext $context
     * @return Response
     */
    public function edit(AdminContext $context)
    {
        $this->denyAccessUnlessGranted('ROLE_EDIT_DOSS');
        return parent::edit($context);
    }

    /**
     * Supprime un dossier après vérification des droits et redirige vers le parent.
     *
     * @param AdminContext $context
     * @return KeyValueStore|RedirectResponse|Response
     */
    public function delete(AdminContext $context): KeyValueStore|RedirectResponse|Response
    {
        $this->denyAccessUnlessGranted('ROLE_DROP_DOSS');
        /** @var Dossier $entity */
        $entity = $context->getEntity()->getInstance();
        $entUrl = $this->generator
            ->setAction(Action::DETAIL)
            ->setEntityId($entity->getParent()?->getId() ?? 1);
        $context->getRequest()->query->set(EA::REFERRER, $entUrl->generateUrl());
        return parent::delete($context);
    }

    /**
     * Redirige après sauvegarde selon l'action.
     *
     * @param AdminContext $context
     * @param string $action
     * @return RedirectResponse
     */
    protected function getRedirectResponseAfterSave(AdminContext $context, string $action): RedirectResponse
    {
        $request = $context->getRequest();
        $entityId = $context->getEntity()->getPrimaryKeyValue();
        $parentId = $request->get('folderId') ?? 1;
        $submitButtonName = $request->request->all()['ea']['newForm']['btn'];

        $entityUrl = $this->generator->setAction(Action::DETAIL)->setEntityId($parentId);
        $url = match ($submitButtonName) {
            Action::SAVE_AND_CONTINUE => $this->generator->setAction(Action::EDIT)->setEntityId($entityId)->generateUrl(),
            Action::SAVE_AND_ADD_ANOTHER => $this->generator->setAction(Action::NEW)->set('folderId', $parentId)->generateUrl(),
            default => $context->getReferrer() ?? $entityUrl->generateUrl(),
        };

        return $this->redirect($url);
    }
}
