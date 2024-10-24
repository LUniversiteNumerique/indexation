<?php

namespace App\Controller;

use App\Entity\Etablissement;
use EasyCorp\Bundle\EasyAdminBundle\Config\{Action, Actions, Crud};
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\{DateTimeField, IdField, TextField};
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;

class EtablissementCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Etablissement::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud->setSearchFields(['nom', 'abrege'])->setDefaultSort(['nom' => 'ASC'])->setEntityPermission('ROLE_READ_ETAB');
    }

    public function configureActions(Actions $actions): Actions
    {
        return parent::configureActions($actions)
            ->disable(Action::DETAIL)
            ->add(Crud::PAGE_NEW, Action::INDEX)
            ->setPermission(Action::NEW, 'ROLE_CREA_ETAB')
            ->setPermission(Action::EDIT, 'ROLE_EDIT_ETAB')
            ->setPermission(Action::DELETE, 'ROLE_DROP_ETAB');
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->onlyOnDetail(),
            TextField::new('nom', 'Etablissement'),
            TextField::new('abrege', 'Nom abrégé'),
            DateTimeField::new('creeLe')->onlyOnDetail(),
            DateTimeField::new('editeLe')->onlyOnDetail()
        ];
    }

    public function createIndexQueryBuilder(SearchDto $searchDto, EntityDto $entityDto, FieldCollection $fields, FilterCollection $filters): QueryBuilder
    {
        return parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters)->orderBy('entity.nom', 'ASC');
    }


}
