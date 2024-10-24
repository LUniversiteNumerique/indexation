<?php

namespace App\Controller;

use App\Entity\Univerique;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\{AssociationField,DateTimeField,IdField,TextField};

class UniveriqueCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Univerique::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return parent::configureCrud($crud)->setEntityLabelInSingular('UNT')->setEntityLabelInPlural("UNTs");
    }

    public function configureActions(Actions $actions): Actions
    {
        return parent::configureActions($actions)
            ->add(Crud::PAGE_NEW, Action::INDEX)
            ->remove(Crud::PAGE_NEW,Action::SAVE_AND_ADD_ANOTHER);
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->onlyOnDetail(),
            TextField::new('label'),
            TextField::new('name'),
            AssociationField::new('fields',"Champs disciplinaires")->setSortable(false)
                ->setQueryBuilder(fn(QueryBuilder $qb) => $qb->where('entity.parent is null')->orderBy('entity.nom')),
            DateTimeField::new('creeLe')->hideOnForm(),
            DateTimeField::new('editeLe')->onlyOnDetail()
        ];
    }
}
