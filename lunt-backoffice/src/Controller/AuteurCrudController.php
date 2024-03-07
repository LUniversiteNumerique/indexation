<?php

namespace App\Controller;

use App\Entity\Auteur;
use EasyCorp\Bundle\EasyAdminBundle\Config\{Action, Actions, Crud, Option\SearchMode};
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\{DateTimeField, EmailField, IdField, TextField};

class AuteurCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Auteur::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud->setSearchMode(SearchMode::ANY_TERMS)->setEntityPermission('ROLE_READ_ACTE');
    }

    public function configureActions(Actions $actions): Actions
    {
        return parent::configureActions($actions)
            ->disable(Action::DETAIL)
            ->setPermission(Action::NEW, 'ROLE_CREA_ACTE')
            ->setPermission(Action::EDIT, 'ROLE_EDIT_ACTE')
            ->setPermission(Action::DELETE, 'ROLE_DROP_ACTE');
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();
        if (Crud::PAGE_NEW  === $pageName || $pageName === Crud::PAGE_EDIT) {
            yield TextField::new('prenom');
            yield TextField::new('nom');
        } else yield TextField::new('nomComplet');
        yield EmailField::new('email');
        yield DateTimeField::new('creeLe');
        yield DateTimeField::new('editeLe')->onlyOnDetail();
    }
}
