<?php

namespace App\Controller;

use App\Entity\IndexingConfig;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\{AssociationField, BooleanField, DateTimeField, IntegerField, TextField};

class IndexingConfigCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return IndexingConfig::class;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            DateTimeField::new('scheduleAt','Dernière Execution')->hideOnForm(),
            AssociationField::new('indexCore', 'UNT'),
            TextField::new('frequency','Fréquence')->setHelp("Exemples: '10 min' ou '2 hours' ou '1 day'"),
            IntegerField::new('batchSize','Taille du lot')->setHelp('Batch Size'),
            BooleanField::new('indexType','Notices externes ?'),
            BooleanField::new('fullMode','Réindexation complète ?'),
        ];
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud->setEntityLabelInSingular('Indexation')->setEntityLabelInPlural("Indexations")
            ->setSearchFields(null)->setEntityPermission('ROLE_READ_CORE');
    }
}
