<?php

namespace App\Controller;

use App\Entity\Groupe;
use EasyCorp\Bundle\EasyAdminBundle\Config\{Action, Actions, Crud};
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\{AssociationField, ChoiceField, DateTimeField, IdField, TextField};

class GroupeCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Groupe::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud->setSearchFields(null)->setEntityPermission('ROLE_READ_GROU');
    }

    public function configureActions(Actions $actions): Actions
    {
        $deleting = static fn(Action $a) => $a->displayIf(static fn (Groupe $g) => $g->getUsers()->isEmpty());
        return parent::configureActions($actions) //->disable(Action::DETAIL)
            ->setPermission(Action::NEW, 'ROLE_CREA_GROU')
            ->setPermission(Action::EDIT, 'ROLE_EDIT_GROU')
            ->setPermission(Action::DELETE, 'ROLE_DROP_GROU')
            ->remove(Crud::PAGE_NEW,Action::SAVE_AND_ADD_ANOTHER)
            ->update(Crud::PAGE_DETAIL, Action::DELETE, $deleting)
            ->update(Crud::PAGE_INDEX, Action::DELETE, $deleting);
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->onlyOnDetail(),
            TextField::new('label','Libellé'),
            ChoiceField::new('rights','Permissions')
                ->setChoices(Groupe::PERMISSIONS)
                ->allowMultipleChoices()->renderAsBadges()->setSortable(false),
            AssociationField::new('users','Utilisateurs')
                ->setFormTypeOption('by_reference', false)->hideOnForm(),
            DateTimeField::new('creeLe')->hideOnForm(),
            DateTimeField::new('editeLe')->onlyOnDetail()
        ];
    }
}
