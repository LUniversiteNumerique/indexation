<?php

namespace App\Controller;

use App\Entity\Univerique;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\{AssociationField,DateTimeField,IdField,TextField};

class UniveriqueCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Univerique::class;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->hideOnForm(),
            TextField::new('label'),
            TextField::new('name'),
            AssociationField::new('fields',"Champs disciplinaires")
                ->setQueryBuilder(fn(QueryBuilder $qb) => $qb->where('entity.parent is null')),
            DateTimeField::new('creeLe')->hideOnForm(),
            DateTimeField::new('editeLe')->onlyOnDetail()
        ];
    }
}
