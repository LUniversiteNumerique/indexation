<?php

namespace App\Controller;

use App\Entity\User;
use App\Event\UserPassSettingEvent;
use App\Field\EntityField;
use DateMalformedStringException;
use DateTimeImmutable;
use Doctrine\ORM\{EntityManagerInterface, QueryBuilder};
use EasyCorp\Bundle\EasyAdminBundle\Config\{Action, Actions, Crud, Filters};
use EasyCorp\Bundle\EasyAdminBundle\Collection\{FieldCollection, FilterCollection};
use EasyCorp\Bundle\EasyAdminBundle\Dto\{EntityDto, SearchDto};
use EasyCorp\Bundle\EasyAdminBundle\{Controller\AbstractCrudController, Context\AdminContext, Filter\DateTimeFilter, Router\AdminUrlGenerator};
use EasyCorp\Bundle\EasyAdminBundle\Field\{BooleanField, DateTimeField, EmailField, FormField, IdField, TextField};
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Security\Csrf\TokenGenerator\TokenGeneratorInterface;

/**
 * Contrôleur CRUD pour la gestion des utilisateurs dans EasyAdmin.
 */
class UserCrudController extends AbstractCrudController
{
    /**
     * @param TokenGeneratorInterface $tokGenerator Générateur de tokens CSRF
     * @param AdminUrlGenerator $urlGenerator Générateur d'URL pour EasyAdmin
     * @param EventDispatcherInterface $dispatcher Dispatcher d'événements Symfony
     */
    public function __construct(
        private readonly TokenGeneratorInterface $tokGenerator,
        private readonly AdminUrlGenerator $urlGenerator,
        private readonly EventDispatcherInterface $dispatcher
    ) {}

    /**
     * Retourne le FQCN de l'entité gérée.
     *
     * @return string
     */
    public static function getEntityFqcn(): string
    {
        return User::class;
    }

    /**
     * Configure les filtres disponibles dans la liste.
     *
     * @param Filters $filters
     * @return Filters
     */
    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add('name')
            ->add('email')
            ->add('enabled')
            ->add('group')
            ->add('school')
            ->add('untheme')
            ->add(DateTimeFilter::new('creeLe','Date Création'));
    }

    /**
     * Configure les options du CRUD.
     *
     * @param Crud $crud
     * @return Crud
     */
    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('utilisateur')
            ->setEntityLabelInPlural('Utilisateurs')
            ->setSearchFields(['name', 'email'])
            ->setDefaultSort(['name' => 'ASC'])
            ->setEntityPermission('ROLE_READ_USER')
            ->setFormOptions([
                'attr' => ['data-controller'=>"user-creating", 'data-user-creating-target'=>"form"]
            ]);
    }

    /**
     * Configure les actions disponibles.
     *
     * @param Actions $actions
     * @return Actions
     */
    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->setPermission(Action::INDEX, 'ROLE_READ_USER')
            ->setPermission(Action::DETAIL, 'ROLE_READ_USER')
            ->setPermission(Action::NEW, 'ROLE_CREA_USER')
            ->setPermission(Action::EDIT, 'ROLE_EDIT_USER')
            ->setPermission(Action::DELETE, 'ROLE_DROP_USER')
            ->add(Crud::PAGE_NEW, Action::INDEX)
            ->add(Crud::PAGE_EDIT, Action::DETAIL)
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->update(Crud::PAGE_EDIT, Action::DETAIL, fn (Action $action) => $action->setIcon('fa fa-undo')->setLabel('Annuler les modifications'))
            ->remove(Crud::PAGE_INDEX, Action::DELETE)
            ->remove(Crud::PAGE_EDIT, Action::SAVE_AND_CONTINUE);
    }

    /**
     * Configure les champs affichés selon la page.
     *
     * @param string $pageName
     * @return iterable
     */
    public function configureFields(string $pageName): iterable
    {
        yield FormField::addColumn(6);
        yield FormField::addFieldset();
        yield IdField::new('id')
            ->onlyOnDetail();
        yield TextField::new('name','Nom');
        yield EmailField::new('email')
            ->setSortable(false);
        yield BooleanField::new('enabled','Statut')
            ->setSortable(false);
        yield DateTimeField::new('creeLe', 'Date de création')
            ->onlyOnDetail();
        yield DateTimeField::new('editeLe', 'Date de modification')
            ->onlyOnDetail();

        yield FormField::addColumn(6);
        yield FormField::addFieldset();
        yield EntityField::new('group','Groupe')
            ->setFormTypeOption('attr', ['data-user-creating-target' => 'masterSelect',]);
        yield EntityField::new('school','Etablissement Contributeur')
            ->setRequired(true);
        yield EntityField::new('untheme',"UNT Documentaliste")
            ->setRequired(true);
    }

    /**
     * Personnalise la requête d'index.
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
            ->join('entity.group','g')
            ->leftJoin('entity.school','e')
            ->leftJoin('entity.untheme','f')
            ->select('entity,g,e,f');
    }

    /**
     * Persiste une nouvelle entité User.
     *
     * @param EntityManagerInterface $entityManager
     * @param User $entityInstance
     * @return void
     * @throws DateMalformedStringException
     */
    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        $token = $this->tokGenerator->generateToken();
        $entityInstance->setReseToken($token);

        $expiration = new DateTimeImmutable(User::VALIDATIME_TOKEN . ' min');
        $entityInstance->setTokenExpiresAt($expiration);

        $this->dispatcher->dispatch(new UserPassSettingEvent($entityInstance, true));

        parent::persistEntity($entityManager, $entityInstance);
    }

    /**
     * Gère la redirection après sauvegarde.
     *
     * @param AdminContext $context
     * @param string $action
     * @return RedirectResponse
     */
    protected function getRedirectResponseAfterSave(AdminContext $context, string $action): RedirectResponse
    {
        $requestData = $context->getRequest()->request->all();
        $submitButtonName = $requestData['ea']['newForm']['btn'] ?? null;
        $entityId = $context->getEntity()->getPrimaryKeyValue();

        if (!$submitButtonName) {
            // Valeur par défaut si le bouton n'est pas trouvé
            return $this->redirect($this->generateUrl($context->getDashboardRouteName()));
        }

        return match ($submitButtonName) {
            Action::SAVE_AND_CONTINUE => $this->redirect(
                $this->urlGenerator
                    ->setAction(Action::EDIT)
                    ->setEntityId($entityId)
                    ->generateUrl()
            ),
            Action::SAVE_AND_RETURN => $this->redirect(
                $context->getReferrer() ?? $this->urlGenerator
                    ->setAction(Action::DETAIL)
                    ->setEntityId($entityId)
                    ->generateUrl()
            ),
            Action::SAVE_AND_ADD_ANOTHER => $this->redirect(
                $this->urlGenerator
                    ->setAction(Action::NEW)
                    ->generateUrl()
            ),
            default => $this->redirect(
                $this->generateUrl($context->getDashboardRouteName())
            ),
        };
    }

    /**
     * Affiche la liste des utilisateurs.
     *
     * @param AdminContext $context
     * @return mixed
     */
    public function index(AdminContext $context)
    {
        $this->denyAccessUnlessGranted('ROLE_READ_USER');
        return parent::index($context);
    }

    /**
     * Affiche le formulaire de création.
     *
     * @param AdminContext $context
     * @return mixed
     */
    public function new(AdminContext $context)
    {
        $this->denyAccessUnlessGranted('ROLE_CREA_USER');
        return parent::new($context);
    }

    /**
     * Affiche le détail d'un utilisateur.
     *
     * @param AdminContext $context
     * @return mixed
     */
    public function detail(AdminContext $context)
    {
        $this->denyAccessUnlessGranted('ROLE_READ_USER');
        return parent::detail($context);
    }

    /**
     * Affiche le formulaire d'édition.
     *
     * @param AdminContext $context
     * @return mixed
     */
    public function edit(AdminContext $context)
    {
        $this->denyAccessUnlessGranted('ROLE_EDIT_USER');
        return parent::edit($context);
    }

    /**
     * Supprime un utilisateur.
     *
     * @param AdminContext $context
     * @return mixed
     */
    public function delete(AdminContext $context)
    {
        $this->denyAccessUnlessGranted('ROLE_DROP_USER');
        return parent::delete($context);
    }
}
