<?php

namespace App\Controller;

use App\Entity\User;
use EasyCorp\Bundle\EasyAdminBundle\Config\{Action, Actions, Crud};
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Collection\{FieldCollection,FilterCollection};
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\{AssociationField, EmailField, FormField, IdField, TextField};
use EasyCorp\Bundle\EasyAdminBundle\Dto\{EntityDto,SearchDto};
use Symfony\Component\Form\Extension\Core\Type\{PasswordType,RepeatedType};

class UserCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return User::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud->setEntityPermission('ROLE_ADMIN');
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->remove(Crud::PAGE_INDEX, Action::EDIT)
            ->remove(Crud::PAGE_INDEX, Action::DELETE);
    }

    public function configureFields(string $pageName): iterable
    {
        yield FormField::addColumn('col-xl-8 col-xxl-8');
        yield FormField::addFieldset();
        yield IdField::new('id')->hideOnForm();
        yield TextField::new('name');
        yield EmailField::new('email'); //TelephoneField::new('phone'),
        yield TextField::new('password')
            ->setFormType(RepeatedType::class)
            ->setFormTypeOptions([
                'type' => PasswordType::class,
                'first_options' => ['label' => 'Password'],
                'second_options' => ['label' => '(Repeat)'],
                'mapped' => false,
            ])->onlyOnForms()
            ->setRequired($pageName === Crud::PAGE_NEW);

        yield FormField::addColumn('col-xl-4 col-xxl-4');
        yield FormField::addFieldset();
        yield AssociationField::new('group');//BooleanField::new('enabled')->hideOnForm(),
        yield AssociationField::new('school');//DateTimeField::new('createdAt')->onlyOnDetail(),
        yield AssociationField::new('fields');//DateTimeField::new('lastLoginAt')->onlyOnDetail(),
        //ImageField::new('avatar')->setBasePath('/media/')
    }

    public function createIndexQueryBuilder(SearchDto $searchDto, EntityDto $entityDto, FieldCollection $fields, FilterCollection $filters): QueryBuilder
    {
        return parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters)
            ->join('entity.group','g')
            ->leftJoin('entity.school','e')
            ->leftJoin('entity.fields','f')
            ->select('entity,g,e,f');
    }
}
