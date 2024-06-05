<?php

namespace App\Controller;

use App\Entity\IndexingConfig;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\{AssociationField, BooleanField, IdField, IntegerField, TextField};

class IndexingConfigCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return IndexingConfig::class;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->hideOnForm(),
            AssociationField::new('indexCore'),
            TextField::new('frequency'),
            IntegerField::new('batchSize'),
            BooleanField::new('indexType'),
            BooleanField::new('fullMode'),
        ];
    }


}
