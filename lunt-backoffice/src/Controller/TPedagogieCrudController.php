<?php

namespace App\Controller;

use App\Entity\TPedagogie;
use EasyCorp\Bundle\EasyAdminBundle\Config\{Action,Actions,Crud,Filters};
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\{DateTimeField,IdField,TextField};
use EasyCorp\Bundle\EasyAdminBundle\Filter\{DateTimeFilter,TextFilter};
use Symfony\Component\HttpFoundation\Response;

/**
 * Contrôleur CRUD pour l'entité TPedagogie dans EasyAdmin.
 */
class TPedagogieCrudController extends AbstractCrudController
{
    /**
     * Retourne le FQCN de l'entité gérée.
     *
     * @return string
     */
    public static function getEntityFqcn(): string
    {
        return TPedagogie::class;
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
            ->setSearchFields(['code', 'nom'])
            ->setDefaultSort(['nom' => 'ASC'])
            ->setEntityLabelInPlural('Types pédagogiques')
            ->setEntityLabelInSingular('type pédagogique')
            ->setEntityPermission('ROLE_READ_TPED');
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
            ->add(TextFilter::new('code'))
            ->add(TextFilter::new('nom'))
            ->add(DateTimeFilter::new('creeLe', 'Date Création'));
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
            ->disable(Action::BATCH_DELETE)
            ->add(Crud::PAGE_NEW, Action::INDEX);
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
            TextField::new('code'),
            TextField::new('nom'),
            TextField::new('suplom'),
            DateTimeField::new('creeLe')->onlyOnDetail(),
            DateTimeField::new('editeLe')->onlyOnDetail(),
        ];
    }

    /**
     * Affiche la liste des entités.
     *
     * @param AdminContext $context
     * @return Response
     */
    public function index(AdminContext $context)
    {
        $this->denyAccessUnlessGranted('ROLE_READ_TPED');
        return parent::index($context);
    }

    /**
     * Crée une nouvelle entité.
     *
     * @param AdminContext $context
     * @return Response
     */
    public function new(AdminContext $context)
    {
        $this->denyAccessUnlessGranted('ROLE_CREA_TPED');
        return parent::new($context);
    }

    /**
     * Affiche le détail d'une entité.
     *
     * @param AdminContext $context
     * @return Response
     */
    public function detail(AdminContext $context)
    {
        $this->denyAccessUnlessGranted('ROLE_READ_TPED');
        return parent::detail($context);
    }

    /**
     * Modifie une entité.
     *
     * @param AdminContext $context
     * @return Response
     */
    public function edit(AdminContext $context)
    {
        $this->denyAccessUnlessGranted('ROLE_EDIT_TPED');
        return parent::edit($context);
    }

    /**
     * Supprime une entité.
     *
     * @param AdminContext $context
     * @return Response
     */
    public function delete(AdminContext $context)
    {
        $this->denyAccessUnlessGranted('ROLE_DROP_TPED');
        return parent::delete($context);
    }
}
