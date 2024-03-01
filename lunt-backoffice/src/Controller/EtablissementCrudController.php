<?php

namespace App\Controller;

use App\Entity\Etablissement;
use EasyCorp\Bundle\EasyAdminBundle\Config\{Action, Actions, Crud};
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\{EmailField, IdField, TextEditorField, TextField};

class EtablissementCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Etablissement::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud->setEntityPermission('ROLE_DOCUM');
    }

    public function configureActions(Actions $actions): Actions
    {
        return parent::configureActions($actions)
            ->disable(Action::DETAIL);
    }

    public function configureFields(string $pageName): iterable
    {

        return [
            IdField::new('id')->onlyOnDetail(),
            TextField::new('nom'),
            TextEditorField::new('description'),
            EmailField::new('email'),
            //DateTimeField::new('createdAt')->onlyOnDetail(),
            //DateTimeField::new('updatedAt')->onlyOnDetail(),
        ];
    }
}
