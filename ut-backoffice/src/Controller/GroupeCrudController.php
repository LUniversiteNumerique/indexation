<?php

namespace App\Controller;

use App\Entity\Groupe;
use EasyCorp\Bundle\EasyAdminBundle\Config\{Action, Actions, Crud};
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\{ChoiceField, IdField, TextField};

class GroupeCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Groupe::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud->setEntityPermission('ROLE_ADMIN');
    }

    public function configureActions(Actions $actions): Actions
    {
        return parent::configureActions($actions)
            ->disable(Action::DETAIL);
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->hideOnForm(),
            TextField::new('label'),
            ChoiceField::new('rights')->allowMultipleChoices()
                ->renderAsBadges(['ROLE_CONTR' => 'success', 'ROLE_DOCUM' => 'warning', 'ROLE_ADMIN' => 'danger'])
                ->setChoices(array_flip(Groupe::PERMISSIONS)),
        ];
    }
}
