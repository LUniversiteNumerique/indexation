<?php

namespace App\Controller;

use App\Entity\Univerique;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\{AssociationField,DateTimeField,IdField,TextField};
use EasyCorp\Bundle\EasyAdminBundle\Filter\{TextFilter, DateTimeFilter};
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;  

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
            ->add(Crud::PAGE_DETAIL, Action::DETAIL)
            ->setPermission(Action::DETAIL, 'ROLE_READ_UNIV')
            ->setPermission(Action::INDEX, 'ROLE_READ_UNIV')
            ->setPermission(Action::EDIT, 'ROLE_EDIT_UNIV')
            ->setPermission(Action::NEW, 'ROLE_CREA_UNIV')
            ->setPermission(Action::DELETE, 'ROLE_DROP_UNIV');
    }

    public function configureFields(string $pageName): iterable
{
    return [
        IdField::new('id')->onlyOnDetail(),
        TextField::new('label'),
        TextField::new('name', "Nom"),
        AssociationField::new('fields', "Champs disciplinaires")
            ->setSortable(false)
            ->setQueryBuilder(fn(QueryBuilder $qb) => $qb->where('entity.parent is null')->orderBy('entity.nom'))
            ->setTemplatePath('badge/fields.html.twig'),  // Utilisation du template personnalisé
        DateTimeField::new('creeLe')->hideOnForm(),
        DateTimeField::new('editeLe')->onlyOnDetail(),
    ];
}

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(TextFilter::new('label')->setLabel('Label'))
            ->add(TextFilter::new('name')->setLabel('Nom'))
            ->add(DateTimeFilter::new('creeLe')->setLabel('Créé le'));
    }
}
