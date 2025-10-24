<?php

namespace App\Controller;

use App\Entity\Auteur;
use EasyCorp\Bundle\EasyAdminBundle\Config\{Action, Actions, Crud, Filters, Option\SearchMode};
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\{DateTimeField, EmailField, IdField, TextField};
use Symfony\Component\HttpFoundation\Response;
use EasyCorp\Bundle\EasyAdminBundle\Filter\{TextFilter, DateTimeFilter};

/**
 * Contrôleur CRUD pour l'entité Auteur dans EasyAdmin.
 */
class AuteurCrudController extends AbstractCrudController
{
    /**
     * Retourne le FQCN de l'entité gérée.
     *
     * @return string
     */
    public static function getEntityFqcn(): string
    {
        return Auteur::class;
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
            ->setSearchMode(SearchMode::ANY_TERMS)
            ->setEntityLabelInPlural('Auteurs')
            ->setEntityLabelInSingular('Auteur')
            ->setEntityPermission('ROLE_READ_ACTE');
    }

    /**
     * Configure les actions disponibles et leurs permissions.
     *
     * @param Actions $actions
     * @return Actions
     */
    public function configureActions(Actions $actions): Actions
    {
        return parent::configureActions($actions)
            ->setPermission(Action::DETAIL, 'ROLE_READ_ACTE')
            ->setPermission(Action::INDEX, 'ROLE_READ_ACTE')
            ->setPermission(Action::NEW, 'ROLE_CREA_ACTE')
            ->setPermission(Action::EDIT, 'ROLE_EDIT_ACTE')
            ->setPermission(Action::DELETE, 'ROLE_DROP_ACTE')
            ->setPermission(Action::BATCH_DELETE, 'ROLE_VALI_NOTI')
            ->disable(Action::DETAIL)
            ->add(Crud::PAGE_NEW, Action::INDEX)
            ->remove(Crud::PAGE_NEW, Action::SAVE_AND_ADD_ANOTHER);
    }

    /**
     * Configure les champs affichés selon la page.
     *
     * @param string $pageName
     * @return iterable
     */
    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->onlyOnDetail();
        yield TextField::new('prenom', 'Prénom')->setSortable(true);
        yield TextField::new('nom', 'Nom')->setSortable(true);
        yield EmailField::new('email', 'Email');
        yield DateTimeField::new('creeLe', 'Date de création')->hideOnForm();
        yield DateTimeField::new('editeLe', "Date d'édition")->onlyOnDetail();
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
            ->add(TextFilter::new('prenom', "Prénom de l'auteur"))
            ->add(TextFilter::new('nom', "Nom de l'auteur"))
            ->add(DateTimeFilter::new('creeLe', 'Date de création'));
    }

    /**
     * Affiche la liste des auteurs.
     *
     * @param AdminContext $context
     * @return Response
     */
    public function index(AdminContext $context)
    {
        $this->denyAccessUnlessGranted('ROLE_READ_ACTE');
        return parent::index($context);
    }

    /**
     * Crée un nouvel auteur.
     *
     * @param AdminContext $context
     * @return Response
     */
    public function new(AdminContext $context)
    {
        $this->denyAccessUnlessGranted('ROLE_CREA_ACTE');
        return parent::new($context);
    }

    /**
     * Affiche le détail d'un auteur.
     *
     * @param AdminContext $context
     * @return Response
     */
    public function detail(AdminContext $context)
    {
        $this->denyAccessUnlessGranted('ROLE_READ_ACTE');
        return parent::detail($context);
    }

    /**
     * Modifie un auteur existant.
     *
     * @param AdminContext $context
     * @return Response
     */
    public function edit(AdminContext $context)
    {
        $this->denyAccessUnlessGranted('ROLE_EDIT_ACTE');
        return parent::edit($context);
    }

    /**
     * Supprime un auteur.
     *
     * @param AdminContext $context
     * @return Response
     */
    public function delete(AdminContext $context)
    {
        $this->denyAccessUnlessGranted('ROLE_DROP_ACTE');
        return parent::delete($context);
    }
}
