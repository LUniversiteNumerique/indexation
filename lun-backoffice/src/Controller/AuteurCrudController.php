<?php

namespace App\Controller;

use App\Entity\Auteur;
use EasyCorp\Bundle\EasyAdminBundle\Config\{Action, Actions, Crud};
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\{ArrayField, AssociationField, EmailField, IdField, TextField};
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;

class AuteurCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Auteur::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud->setEntityPermission('ROLE_DOCUM');
    }

    public function configureActions(Actions $actions): Actions
    {
        return parent::configureActions($actions)
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->remove(Crud::PAGE_INDEX, Action::EDIT)
            ->remove(Crud::PAGE_INDEX, Action::DELETE);
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();
        if (Crud::PAGE_NEW  === $pageName || $pageName === Crud::PAGE_EDIT) {
            yield TextField::new('prenom');
            yield TextField::new('nom');
        } else yield TextField::new('nomComplet');
        yield EmailField::new('email');
        yield AssociationField::new('ecole');
        yield ArrayField::new('roles')->onlyOnDetail();
    }

    public function createIndexQueryBuilder(SearchDto $searchDto, EntityDto $entityDto, FieldCollection $fields, FilterCollection $filters): QueryBuilder
    {
        return parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters)
            ->join('entity.ecole','e')->select('entity,e');
    }
}
