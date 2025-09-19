<?php

namespace App\Controller;

use App\Entity\Licence;
use EasyCorp\Bundle\EasyAdminBundle\Config\{Action,Actions,Crud,Filters};
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\{DateTimeField,IdField,TextField};
use EasyCorp\Bundle\EasyAdminBundle\Filter\{DateTimeFilter,TextFilter};
use Symfony\Component\HttpFoundation\Response;

/**
 * Contrôleur CRUD pour l'entité Licence dans EasyAdmin.
 *
 * Gère la configuration des champs, filtres, actions et permissions
 * pour l'administration des licences.
 */
class LicenceCrudController extends AbstractCrudController
{
    /**
     * Retourne le FQCN (nom de classe complet) de l'entité Licence.
     *
     * @return string
     */
    public static function getEntityFqcn(): string
    {
        return Licence::class;
    }

    /**
     * Configure les paramètres du CRUD (recherche, tri, labels, permissions).
     *
     * @param Crud $crud
     * @return Crud
     */
    public function configureCrud(Crud $crud): Crud
    {
        return $crud->setSearchFields(['code', 'valeur'])
            ->setDefaultSort(['valeur' => 'ASC'])
            ->setEntityLabelInPlural("Licences")
            ->setEntityLabelInSingular("licence")
            ->setEntityPermission('ROLE_READ_LICE')
            ->setPageTitle(Action::NEW, fn () => 'Créer une licence')
            ->setPageTitle(Action::EDIT, fn () => 'Modifier une licence');
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
            ->add(TextFilter::new('code'))
            ->add(TextFilter::new('valeur'))
            ->add(DateTimeFilter::new('creeLe','Date Création'));
    }

    /**
     * Configure les actions disponibles dans l'interface.
     *
     * @param Actions $actions
     * @return Actions
     */
    public function configureActions(Actions $actions): Actions
    {
        return parent::configureActions($actions)
            // Chnagement de titre du bouton "Créer" pour prendre en compte le féminin
            ->update(Crud::PAGE_INDEX, Action::NEW, fn (Action $action) => $action->setLabel('Créer une <b>licence</b>'))
            ->disable(Action::BATCH_DELETE)
            ->add(Crud::PAGE_NEW, Action::INDEX);
    }

    /**
     * Configure les champs affichés selon la page (index, détail, édition, création).
     *
     * @param string $pageName
     * @return iterable
     */
    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->onlyOnDetail(),
            TextField::new('code'),
            TextField::new('valeur'),
            DateTimeField::new('creeLe')->onlyOnDetail(),
            DateTimeField::new('editeLe')->onlyOnDetail()
        ];
    }

    /**
     * Affiche la liste des licences.
     * Vérifie la permission ROLE_READ_LICE.
     *
     * @param AdminContext $context
     * @return Response
     */
    public function index(AdminContext $context)
    {
        $this->denyAccessUnlessGranted('ROLE_READ_LICE');
        return parent::index($context);
    }

    /**
     * Affiche le formulaire de création d'une licence.
     * Vérifie la permission ROLE_CREA_LICE.
     *
     * @param AdminContext $context
     * @return Response
     */
    public function new(AdminContext $context)
    {
        $this->denyAccessUnlessGranted('ROLE_CREA_LICE');
        return parent::new($context);
    }

    /**
     * Affiche le détail d'une licence.
     * Vérifie la permission ROLE_READ_LICE.
     *
     * @param AdminContext $context
     * @return Response
     */
    public function detail(AdminContext $context)
    {
        $this->denyAccessUnlessGranted('ROLE_READ_LICE');
        return parent::detail($context);
    }

    /**
     * Affiche le formulaire d'édition d'une licence.
     * Vérifie la permission ROLE_EDIT_LICE.
     *
     * @param AdminContext $context
     * @return Response
     */
    public function edit(AdminContext $context)
    {
        $this->denyAccessUnlessGranted('ROLE_EDIT_LICE');
        return parent::edit($context);
    }

    /**
     * Supprime une licence.
     * Vérifie la permission ROLE_DROP_LICE.
     *
     * @param AdminContext $context
     * @return Response
     */
    public function delete(AdminContext $context)
    {
        $this->denyAccessUnlessGranted('ROLE_DROP_LICE');
        return parent::delete($context);
    }
}
