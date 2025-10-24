<?php

namespace App\Controller;

use App\Entity\Univerique;
use App\Event\AfterUntCreatedEvent;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Config\{Action, Actions, Crud, Filters};
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\{AssociationField, DateTimeField, IdField, TextField, UrlField};
use Psr\EventDispatcher\EventDispatcherInterface;
use EasyCorp\Bundle\EasyAdminBundle\Filter\{TextFilter, DateTimeFilter};

/**
 * Contrôleur EasyAdmin pour la gestion CRUD de l'entité Univerique.
 */
class UniveriqueCrudController extends AbstractCrudController
{
    /**
     * @param EventDispatcherInterface $dispatcher Le dispatcher d'événements.
     */
    public function __construct(
        private readonly EventDispatcherInterface $dispatcher
    ) {}

    /**
     * Retourne le FQCN de l'entité gérée.
     *
     * @return string
     */
    public static function getEntityFqcn(): string
    {
        return Univerique::class;
    }

    /**
     * Configure les options CRUD (libellés, titres, etc.).
     *
     * @param Crud $crud
     * @return Crud
     */
    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('UNT')
            ->setEntityLabelInPlural('UNT')
            ->setPageTitle(Action::NEW, fn () => 'Créer une UNT')
            ->setPageTitle(Action::EDIT, fn () => 'Modifier une UNT');
    }

    /**
     * Configure les actions disponibles et leurs permissions.
     *
     * @param Actions $actions
     * @return Actions
     */
    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->setPermission(Action::DETAIL, 'ROLE_READ_UNIV')
            ->setPermission(Action::INDEX, 'ROLE_READ_UNIV')
            ->setPermission(Action::EDIT, 'ROLE_EDIT_UNIV')
            ->setPermission(Action::NEW, 'ROLE_CREA_UNIV')
            ->setPermission(Action::DELETE, 'ROLE_DROP_UNIV')
            ->add(Crud::PAGE_NEW, Action::INDEX)
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->update(Crud::PAGE_NEW, Action::SAVE_AND_ADD_ANOTHER, fn (Action $action) => $action->setLabel('Créer et ajouter une <b>nouvelle</b>'))
            ->update(Crud::PAGE_INDEX, Action::NEW, fn (Action $action) => $action->setLabel('Créer une <b>UNT</b>'))
            ->remove(Crud::PAGE_INDEX, Action::EDIT)
            ->disable(Action::DELETE);
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
            IdField::new('id')
                ->onlyOnDetail(),
            TextField::new('label', 'Nom'),
            UrlField::new('siteWeb', 'Lien du site web'),
            TextField::new('name', 'Nom du répertoire')
                ->hideWhenUpdating(),
            AssociationField::new('fields', 'Champs disciplinaires')
                ->setTemplatePath('admin/fields/badge.html.twig')
                ->setQueryBuilder(fn(QueryBuilder $qb) => $qb->where('entity.parent is null')->orderBy('entity.nom'))
                ->setSortable(false),
            DateTimeField::new('creeLe', 'Date de création')
                ->hideOnForm(),
            DateTimeField::new('editeLe', "Date d'édition")
                ->onlyOnDetail(),
        ];
    }

    /**
     * Configure les filtres disponibles.
     *
     * @param Filters $filters
     * @return Filters
     */
    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(TextFilter::new('label', 'Nom'))
            ->add(TextFilter::new('name', 'Répertoire'))
            ->add(DateTimeFilter::new('creeLe', 'Date de création'));
    }

    /**
     * Persiste une entité et déclenche un événement personnalisé.
     *
     * @param EntityManagerInterface $entityManager
     * @param mixed $entityInstance
     * @return void
     */
    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        parent::persistEntity($entityManager, $entityInstance);
        $this->dispatcher->dispatch(new AfterUntCreatedEvent($entityInstance));
    }

    /**
     * Affiche la liste des entités avec contrôle d'accès.
     *
     * @param AdminContext $context
     * @return mixed
     */
    public function index(AdminContext $context)
    {
        $this->denyAccessUnlessGranted('ROLE_READ_UNIV');
        return parent::index($context);
    }

    /**
     * Affiche le formulaire de création avec contrôle d'accès.
     *
     * @param AdminContext $context
     * @return mixed
     */
    public function new(AdminContext $context)
    {
        $this->denyAccessUnlessGranted('ROLE_CREA_UNIV');
        return parent::new($context);
    }

    /**
     * Affiche le détail d'une entité avec contrôle d'accès.
     *
     * @param AdminContext $context
     * @return mixed
     */
    public function detail(AdminContext $context)
    {
        $this->denyAccessUnlessGranted('ROLE_READ_UNIV');
        return parent::detail($context);
    }

    /**
     * Affiche le formulaire d'édition avec contrôle d'accès.
     *
     * @param AdminContext $context
     * @return mixed
     */
    public function edit(AdminContext $context)
    {
        $this->denyAccessUnlessGranted('ROLE_EDIT_UNIV');
        return parent::edit($context);
    }

    /**
     * Supprime une entité avec contrôle d'accès.
     *
     * @param AdminContext $context
     * @return mixed
     */
    public function delete(AdminContext $context)
    {
        $this->denyAccessUnlessGranted('ROLE_DROP_UNIV');
        return parent::delete($context);
    }
}
