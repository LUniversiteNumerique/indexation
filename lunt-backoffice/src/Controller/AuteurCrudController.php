<?php

namespace App\Controller;

use App\Entity\Auteur;
use EasyCorp\Bundle\EasyAdminBundle\Config\{Action, Actions, Crud, Filters, Option\SearchMode};
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\{AssociationField, DateTimeField, EmailField, IdField, TextField};
use Symfony\Component\Form\Extension\Core\Type\DateType;
use EasyCorp\Bundle\EasyAdminBundle\Filter\{TextFilter, DateTimeFilter};

class AuteurCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Auteur::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud->setSearchMode(SearchMode::ANY_TERMS)
            ->setEntityLabelInPlural("Auteurs")->setEntityLabelInSingular('auteur')
            ->setEntityPermission('ROLE_READ_ACTE');
    }

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
            ->remove(Crud::PAGE_NEW,Action::SAVE_AND_ADD_ANOTHER);
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();
        yield TextField::new('prenom', 'Prénom')->setSortable(true);
        yield TextField::new('nom')->setSortable(true);
        yield EmailField::new('email');
        yield AssociationField::new('etablissement');
        yield DateTimeField::new('creeLe', 'Date de création')->hideOnForm();
        yield DateTimeField::new('editeLe', "Date d'édition'")->onlyOnDetail();
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(TextFilter::new('prenom',"Prénom de l'auteur"))
            ->add(TextFilter::new('nom',"Nom de l'auteur"))
            ->add(DateTimeFilter::new('creeLe','Date de création')->setFormTypeOption('value_type', DateType::class));
    }

    public function index(AdminContext $context)
    {
        $this->denyAccessUnlessGranted('ROLE_READ_ACTE');
        return parent::index($context);
    }

    public function new(AdminContext $context)
    {
        $this->denyAccessUnlessGranted('ROLE_CREA_ACTE');
        return parent::new($context);
    }

    public function detail(AdminContext $context)
    {
        $this->denyAccessUnlessGranted('ROLE_READ_ACTE');
        return parent::detail($context);
    }

    public function edit(AdminContext $context)
    {
        $this->denyAccessUnlessGranted('ROLE_EDIT_ACTE');
        return parent::edit($context);
    }

    public function delete(AdminContext $context)
    {
        $this->denyAccessUnlessGranted('ROLE_DROP_ACTE');
        return parent::delete($context);
    }
}
