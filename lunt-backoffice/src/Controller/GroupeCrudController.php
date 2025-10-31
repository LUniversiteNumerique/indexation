<?php

namespace App\Controller;

use App\Entity\Groupe;
use EasyCorp\Bundle\EasyAdminBundle\Config\{Action, Actions, Crud};
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\{AssociationField, ChoiceField, DateTimeField, IdField, TextField};
use Symfony\Component\HttpFoundation\Response;

/**
 * Contrôleur CRUD pour l'entité Groupe dans EasyAdmin.
 */
class GroupeCrudController extends AbstractCrudController
{
    /**
     * Retourne le FQCN de l'entité gérée.
     *
     * @return string
     */
    public static function getEntityFqcn(): string
    {
        return Groupe::class;
    }

    /**
     * Configure les options du CRUD.
     *
     * @param Crud $crud
     * @return Crud
     */
    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setSearchFields(null)
            ->setEntityLabelInPlural('Rôles et permissions')
            ->setEntityLabelInSingular('rôle')
            ->setEntityPermission('ROLE_READ_GROU');
    }

    /**
     * Configure les actions disponibles et leurs permissions.
     *
     * @param Actions $actions
     * @return Actions
     */
    public function configureActions(Actions $actions): Actions
    {
        $deleting = static fn(Action $a) => $a->displayIf(
            static fn(Groupe $g) => $g->getUsers()->isEmpty()
        );

        return parent::configureActions($actions)
            ->setPermission(Action::DETAIL, 'ROLE_READ_GROU')
            ->setPermission(Action::INDEX, 'ROLE_READ_GROU')
            ->setPermission(Action::NEW, 'ROLE_CREA_GROU')
            ->setPermission(Action::EDIT, 'ROLE_EDIT_GROU')
            ->setPermission(Action::DELETE, 'ROLE_DROP_GROU')
            ->update(Crud::PAGE_DETAIL, Action::DELETE, $deleting)
            ->update(Crud::PAGE_INDEX, Action::DELETE, $deleting)
            ->disable(Action::NEW, Action::DELETE);
    }

    /**
     * Configure les champs affichés selon la page.
     *
     * @param string $pageName
     * @return iterable
     */
    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')
                ->onlyOnDetail(),
            TextField::new('label', 'Libellé'),
            ChoiceField::new('rights', 'Permissions')
                ->setChoices(Groupe::PERMISSIONS)
                ->allowMultipleChoices()
                ->renderAsBadges()
                ->setSortable(false),
            AssociationField::new('users', 'Utilisateurs')
                ->setFormTypeOption('by_reference', false)
                ->hideOnForm(),
            DateTimeField::new('creeLe')
                ->hideOnForm(),
            DateTimeField::new('editeLe')
                ->onlyOnDetail(),
        ];
    }

    /**
     * Affiche la liste des groupes.
     *
     * @param AdminContext $context
     * @return Response
     */
    public function index(AdminContext $context)
    {
        $this->denyAccessUnlessGranted('ROLE_READ_GROU');
        return parent::index($context);
    }

    /**
     * Crée un nouveau groupe.
     *
     * @param AdminContext $context
     * @return Response
     */
    public function new(AdminContext $context)
    {
        $this->denyAccessUnlessGranted('ROLE_CREA_GROU');
        return parent::new($context);
    }

    /**
     * Affiche le détail d'un groupe.
     *
     * @param AdminContext $context
     * @return Response
     */
    public function detail(AdminContext $context)
    {
        $this->denyAccessUnlessGranted('ROLE_READ_GROU');
        return parent::detail($context);
    }

    /**
     * Modifie un groupe existant.
     *
     * @param AdminContext $context
     * @return Response
     */
    public function edit(AdminContext $context)
    {
        $this->denyAccessUnlessGranted('ROLE_EDIT_GROU');
        return parent::edit($context);
    }

    /**
     * Supprime un groupe.
     *
     * @param AdminContext $context
     * @return Response
     */
    public function delete(AdminContext $context)
    {
        $this->denyAccessUnlessGranted('ROLE_DROP_GROU');
        return parent::delete($context);
    }
}
