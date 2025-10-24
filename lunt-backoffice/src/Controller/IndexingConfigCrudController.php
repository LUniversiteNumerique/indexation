<?php

namespace App\Controller;

use App\Entity\DateUnit;
use App\Entity\IndexingConfig;
use EasyCorp\Bundle\EasyAdminBundle\Config\{Action,Actions,Crud};
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\{AssociationField, BooleanField, ChoiceField, DateTimeField, IntegerField, TextField};

/**
 * Contrôleur CRUD pour la gestion des configurations d'indexation.
 */
class IndexingConfigCrudController extends AbstractCrudController
{
    /**
     * Retourne le FQCN de l'entité gérée.
     *
     * @return string
     */
    public static function getEntityFqcn(): string
    {
        return IndexingConfig::class;
    }

    /**
     * Configure les actions disponibles dans l'interface d'administration.
     *
     * @param Actions $actions
     * @return Actions
     */
    public function configureActions(Actions $actions): Actions
    {
        return parent::configureActions($actions)
            ->disable(Action::BATCH_DELETE)
            ->add(Crud::PAGE_NEW, Action::INDEX)
            ->update(Crud::PAGE_INDEX, Action::NEW, fn(Action $action) => $action->setLabel('Créer une <b>indexation</b>'))
            ->update(Crud::PAGE_NEW, Action::SAVE_AND_ADD_ANOTHER, fn(Action $action) => $action->setLabel('Créer et ajouter une <b>nouvelle</b>'))
            ->setPermission(Action::NEW, 'ROLE_CREA_CORE')
            ->setPermission(Action::EDIT, 'ROLE_EDIT_CORE')
            ->setPermission(Action::DELETE, 'ROLE_DROP_CORE')
            ->setPermission(Action::DETAIL, 'ROLE_READ_CORE')
            ->setPermission(Action::INDEX, 'ROLE_READ_CORE');
    }

    /**
     * Configure les champs affichés selon la page.
     *
     * @param string $pageName
     * @return iterable
     */
    public function configureFields(string $pageName): iterable
    {
        yield AssociationField::new('indexCore', 'UNT');

        if ($pageName === Crud::PAGE_NEW || $pageName === Crud::PAGE_EDIT) {
            yield IntegerField::new('frequency.valeur', "Fréquence d'exécution");
            yield ChoiceField::new('frequency.unite', 'Unité de la Fréquence')
                ->setChoices(DateUnit::cases());
        } else {
            yield TextField::new('frequency', 'Fréquence')
                ->setSortable(false);
        }

        yield IntegerField::new('batchSize', 'Taille du lot')
            ->setHelp('Batch Size');
        yield BooleanField::new('indexType', "Indexation externe ?")
            ->renderAsSwitch(false)
            ->setSortable(false)
            ->hideWhenUpdating();
        yield BooleanField::new('fullMode', 'Réindexation complète ?')
            ->setSortable(false);
        yield DateTimeField::new('scheduleAt', 'Dernière Execution')
            ->hideOnForm();
    }

    /**
     * Configure le CRUD (libellés, permissions, titres...).
     *
     * @param Crud $crud
     * @return Crud
     */
    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('indexation')
            ->setEntityLabelInPlural('Indexations')
            ->setSearchFields(null)
            ->setEntityPermission('ROLE_READ_CORE')
            ->setPageTitle(Action::NEW, fn() => 'Créer une indexation')
            ->setPageTitle(Action::EDIT, fn() => 'Modifier une indexation');
    }

    /**
     * Affiche la liste des indexations.
     *
     * @param AdminContext $context
     * @return mixed
     */
    public function index(AdminContext $context)
    {
        $this->denyAccessUnlessGranted('ROLE_READ_CORE');
        return parent::index($context);
    }

    /**
     * Crée une nouvelle indexation.
     *
     * @param AdminContext $context
     * @return mixed
     */
    public function new(AdminContext $context)
    {
        $this->denyAccessUnlessGranted('ROLE_CREA_CORE');
        return parent::new($context);
    }

    /**
     * Affiche le détail d'une indexation.
     *
     * @param AdminContext $context
     * @return mixed
     */
    public function detail(AdminContext $context)
    {
        $this->denyAccessUnlessGranted('ROLE_READ_CORE');
        return parent::detail($context);
    }

    /**
     * Modifie une indexation existante.
     *
     * @param AdminContext $context
     * @return mixed
     */
    public function edit(AdminContext $context)
    {
        $this->denyAccessUnlessGranted('ROLE_EDIT_CORE');
        return parent::edit($context);
    }

    /**
     * Supprime une indexation.
     *
     * @param AdminContext $context
     * @return mixed
     */
    public function delete(AdminContext $context)
    {
        $this->denyAccessUnlessGranted('ROLE_DROP_CORE');
        return parent::delete($context);
    }
}
