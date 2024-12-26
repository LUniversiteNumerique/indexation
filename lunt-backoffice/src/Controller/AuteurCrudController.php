<?php

namespace App\Controller;

use App\Entity\Auteur;
use EasyCorp\Bundle\EasyAdminBundle\Config\{Action, Actions, Crud, Option\SearchMode};
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\{DateTimeField, EmailField, IdField, TextField};
use EasyCorp\Bundle\EasyAdminBundle\Filter\{TextFilter, DateTimeFilter};
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;  

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
            ->setPermission(Action::DELETE, 'ROLE_DROP_ACTE')
            ->update(Crud::PAGE_INDEX, Action::NEW, fn (Action $action) => $action->setLabel('Créer un auteur'))
            ->remove(Crud::PAGE_NEW,Action::SAVE_AND_ADD_ANOTHER);
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();
        if (Crud::PAGE_NEW  === $pageName || $pageName === Crud::PAGE_EDIT) {
            yield TextField::new('prenom')->setSortable(true);
            yield TextField::new('nom')->setSortable(true);
        } else {
            yield TextField::new('prenom')->setSortable(true);
            yield TextField::new('nom')->setSortable(true);
        }
        yield EmailField::new('email');
        yield DateTimeField::new('creeLe')->hideOnForm();
        yield DateTimeField::new('editeLe')->onlyOnDetail();
    }

    
    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(TextFilter::new('prenom')->setLabel('Prénom de l\'auteur'))
            ->add(TextFilter::new('nom')->setLabel('Nom de l\'auteur'))
            ->add(DateTimeFilter::new('creeLe')->setLabel('Créé le'));
    }
}
