<?php

namespace App\Controller;

use App\Entity\Discipline;
use App\Field\EntityField;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Config\{Action, Actions, Crud};
use EasyCorp\Bundle\EasyAdminBundle\Collection\{FieldCollection,FilterCollection};
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\{DateTimeField, IdField, TextEditorField, TextField};
use EasyCorp\Bundle\EasyAdminBundle\Dto\{EntityDto,SearchDto};

class DisciplineCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Discipline::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud->setSearchFields(null)->setEntityPermission('ROLE_READ_DISC');
    }

    public function configureActions(Actions $actions): Actions
    {
        return parent::configureActions($actions)
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->remove(Crud::PAGE_INDEX, Action::EDIT)
            ->remove(Crud::PAGE_INDEX, Action::DELETE)
            ->setPermission(Action::NEW, 'ROLE_CREA_DISC')
            ->setPermission(Action::EDIT, 'ROLE_EDIT_DISC')
            ->setPermission(Action::DELETE, 'ROLE_DROP_DISC');
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->onlyOnDetail(),
            TextEditorField::new('code'),
            TextField::new('nom'),
            EntityField::new('parent')->onlyOnForms(),
            EntityField::new('children','Sous-Discipline')->hideOnForm()->setTemplatePath('admin/fields/tree.html.twig'),
            DateTimeField::new('creeLe')->onlyOnDetail(),
            DateTimeField::new('editeLe')->onlyOnDetail()
        ];
    }

    public function createIndexQueryBuilder(SearchDto $searchDto, EntityDto $entityDto, FieldCollection $fields, FilterCollection $filters): QueryBuilder
    {
        return parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters)
            ->leftJoin('entity.children','d')->leftJoin('d.children','s')
            ->andWhere('entity.parent is null')->select('entity,d,s');
    }
}
