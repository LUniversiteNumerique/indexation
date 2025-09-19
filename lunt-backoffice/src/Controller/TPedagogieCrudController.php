<?php

namespace App\Controller;

use App\Entity\TPedagogie;
use EasyCorp\Bundle\EasyAdminBundle\Config\{Action,Actions,Crud,Filters};
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\{DateTimeField,IdField,TextField};
use EasyCorp\Bundle\EasyAdminBundle\Filter\{DateTimeFilter,TextFilter};

class TPedagogieCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return TPedagogie::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud->setSearchFields(['code', 'nom'])->setDefaultSort(['nom' => 'ASC'])
            ->setEntityLabelInPlural("Types pédagogiques")->setEntityLabelInSingular("type pédagogique")
            ->setEntityPermission('ROLE_READ_TPED');
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(TextFilter::new('code'))
            ->add(TextFilter::new('nom'))
            ->add(DateTimeFilter::new('creeLe','Date Création'));
    }

    public function configureActions(Actions $actions): Actions
    {
        return parent::configureActions($actions)
            ->disable(Action::BATCH_DELETE)
            ->add(Crud::PAGE_NEW, Action::INDEX);
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->onlyOnDetail(),
            TextField::new('code'),
            TextField::new('nom'),
            TextField::new('suplom'),
            DateTimeField::new('creeLe')->onlyOnDetail(),
            DateTimeField::new('editeLe')->onlyOnDetail()
        ];
    }

    public function index(AdminContext $context)
    {
        $this->denyAccessUnlessGranted('ROLE_READ_TPED');
        return parent::index($context);
    }

    public function new(AdminContext $context)
    {
        $this->denyAccessUnlessGranted('ROLE_CREA_TPED');
        return parent::new($context);
    }

    public function detail(AdminContext $context)
    {
        $this->denyAccessUnlessGranted('ROLE_READ_TPED');
        return parent::detail($context);
    }

    public function edit(AdminContext $context)
    {
        $this->denyAccessUnlessGranted('ROLE_EDIT_TPED');
        return parent::edit($context);
    }

    public function delete(AdminContext $context)
    {
        $this->denyAccessUnlessGranted('ROLE_DROP_TPED');
        return parent::delete($context);
    }
}
