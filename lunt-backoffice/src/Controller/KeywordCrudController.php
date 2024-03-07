<?php

namespace App\Controller;

use App\Entity\Keyword;
use EasyCorp\Bundle\EasyAdminBundle\Config\{Action, Actions, Crud, Filters};
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\{BooleanField, DateTimeField, IdField, TextField};
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;

class KeywordCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Keyword::class;
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters->add(BooleanFilter::new('valide'));
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud->setEntityLabelInSingular('Mot clé')->setEntityLabelInPlural('Mots clés')
            ->setSearchFields(['nom'])->setEntityPermission('ROLE_READ_KEYW');
    }

    public function configureActions(Actions $actions): Actions
    {
        return parent::configureActions($actions)
            ->disable(Action::DETAIL)
            ->setPermission(Action::NEW, 'ROLE_CREA_KEYW')
            ->setPermission(Action::EDIT, 'ROLE_EDIT_KEYW')
            ->setPermission(Action::DELETE, 'ROLE_DROP_KEYW')
        ;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->onlyOnDetail(),
            TextField::new('nom'),
            BooleanField::new('valide')->renderAsSwitch($this->isGranted('ROLE_EDIT_KEYW')),
            DateTimeField::new('creeLe'),
            DateTimeField::new('editeLe')->onlyOnDetail()
        ];
    }
}
