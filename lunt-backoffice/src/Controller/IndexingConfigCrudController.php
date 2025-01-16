<?php

namespace App\Controller;

use App\Entity\DateUnit;
use App\Entity\IndexingConfig;
use EasyCorp\Bundle\EasyAdminBundle\Config\{Action,Actions,Crud};
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\{AssociationField, BooleanField, ChoiceField, DateTimeField, IntegerField, TextField};

class IndexingConfigCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return IndexingConfig::class;
    }

    public function configureActions(Actions $actions): Actions
    {
        return parent::configureActions($actions)
            ->disable(Action::BATCH_DELETE)
            ->setPermission(Action::NEW, 'ROLE_CREA_CORE')
            ->setPermission(Action::EDIT, 'ROLE_EDIT_CORE')
            ->setPermission(Action::DELETE, 'ROLE_DROP_CORE');
    }

    public function configureFields(string $pageName): iterable
    {
        yield AssociationField::new('indexCore', 'UNT');
        if (Crud::PAGE_NEW  === $pageName || $pageName === Crud::PAGE_EDIT) {
            yield IntegerField::new('frequency.valeur', "Fréquence d'exécution");
            yield ChoiceField::new('frequency.unite', 'Unité de la Fréquence')->setChoices(DateUnit::cases());
        } else yield TextField::new('frequency','Fréquence')->setSortable(false);
        yield IntegerField::new('batchSize','Taille du lot')->setHelp('Batch Size');
        yield BooleanField::new('indexType',"Indexation externe ?")->setSortable(false);
        yield BooleanField::new('fullMode','Réindexation complète ?')->setSortable(false);
        yield DateTimeField::new('scheduleAt','Dernière Execution')->hideOnForm();
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud->setEntityLabelInSingular('indexation')->setEntityLabelInPlural("Indexations")
            ->setSearchFields(null)->setEntityPermission('ROLE_READ_CORE');
    }
}
