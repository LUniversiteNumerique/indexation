<?php

namespace App\Controller;

use App\Entity\Univerique;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Config\{Action,Actions,Crud,Filters};
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\{AssociationField,DateTimeField,IdField,TextField};
use EasyCorp\Bundle\EasyAdminBundle\Filter\{TextFilter, DateTimeFilter};

class UniveriqueCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Univerique::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return parent::configureCrud($crud)->setEntityLabelInSingular('UNT')->setEntityLabelInPlural("UNT");
    }

    public function configureActions(Actions $actions): Actions
    {
        return parent::configureActions($actions)
            ->add(Crud::PAGE_NEW, Action::INDEX)
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->setPermission(Action::DETAIL, 'ROLE_READ_UNIV')
            ->setPermission(Action::INDEX, 'ROLE_READ_UNIV')
            ->setPermission(Action::EDIT, 'ROLE_EDIT_UNIV')
            ->setPermission(Action::NEW, 'ROLE_CREA_UNIV')
            ->setPermission(Action::DELETE, 'ROLE_DROP_UNIV')
            ->remove(Crud::PAGE_INDEX, Action::EDIT)
            ->remove(Crud::PAGE_INDEX, Action::DELETE);
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->onlyOnDetail(),
            TextField::new('label'),
            TextField::new('name', "Nom"),
            AssociationField::new('fields', "Champs disciplinaires")
                ->setSortable(false)->setTemplatePath('badge/fields.html.twig')
                ->setQueryBuilder(fn(QueryBuilder $qb) => $qb->where('entity.parent is null')->orderBy('entity.nom')),
            DateTimeField::new('creeLe', 'Date de création')->hideOnForm(),
            DateTimeField::new('editeLe', "Date d'édition")->onlyOnDetail(),
        ];
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(TextFilter::new('label')->setLabel('Label'))
            ->add(TextFilter::new('name')->setLabel('Nom'))
            ->add(DateTimeFilter::new('creeLe')->setLabel('Date Création'));
    }
}
