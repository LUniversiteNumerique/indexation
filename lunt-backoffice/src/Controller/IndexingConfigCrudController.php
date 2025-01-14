<?php

namespace App\Controller;

use App\Entity\IndexingConfig;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\{AssociationField, BooleanField, DateTimeField, IntegerField, TextField};

class IndexingConfigCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return IndexingConfig::class;
    }

    public function configureActions(Actions $actions): Actions
    {
        return parent::configureActions($actions)
            ->setPermission(Action::NEW, 'ROLE_CREA_CORE')
            ->setPermission(Action::EDIT, 'ROLE_EDIT_CORE')
            ->setPermission(Action::DELETE, 'ROLE_DROP_CORE')
            ->update(Crud::PAGE_INDEX, Action::NEW, fn (Action $a) => $a->setLabel('Créer une <b>Indexation</b>'));
    }


    public function configureFields(string $pageName): iterable
    {
        return [
            AssociationField::new('indexCore', 'UNT'),
            TextField::new('frequency','Fréquence')->setSortable(false)->setHelp("Exemples: '10 min' ou '2 hours' ou '1 day'"),
            IntegerField::new('batchSize','Taille du lot')->setHelp('Batch Size'),
            BooleanField::new('indexType',"Indexation externe ?")->setSortable(false),
            BooleanField::new('fullMode','Réindexation complète ?')->setSortable(false),
            DateTimeField::new('scheduleAt','Dernière Execution')->hideOnForm(),
        ];
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud->setEntityLabelInSingular('indexation')->setEntityLabelInPlural("Indexations")
            ->setSearchFields(null)->setEntityPermission('ROLE_READ_CORE')
            ->setPageTitle(Action::NEW, fn () => 'Créer une <b>Indexation</b>')
            ->setPageTitle(Action::EDIT, fn (IndexingConfig $i) => 'Modifier une <b>Indexation</b>');
    }
}
