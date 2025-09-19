<?php

namespace App\Controller;

use App\Entity\TDocument;
use EasyCorp\Bundle\EasyAdminBundle\Config\{Action,Actions,Crud,Filters};
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\{DateTimeField,IdField,TextField};
use EasyCorp\Bundle\EasyAdminBundle\Filter\{DateTimeFilter,TextFilter};
use Symfony\Component\HttpFoundation\Response;

/**
 * Contrôleur CRUD pour l'entité TDocument dans EasyAdmin.
 *
 * Gère la configuration des champs, filtres, actions et permissions
 * pour l'administration des types de documents.
 */
class TDocumentCrudController extends AbstractCrudController
{
    /**
     * Retourne le FQCN (nom de classe complet) de l'entité TDocument.
     *
     * @return string
     */
    public static function getEntityFqcn(): string
    {
        return TDocument::class;
    }

    /**
     * Configure les paramètres du CRUD (recherche, tri, labels, permissions).
     *
     * @param Crud $crud
     * @return Crud
     */
    public function configureCrud(Crud $crud): Crud
    {
        return $crud->setSearchFields(['code', 'nom'])
            ->setDefaultSort(['nom' => 'ASC'])
            ->setEntityLabelInPlural("Types documentaires")
            ->setEntityLabelInSingular("type documentaire")
            ->setEntityPermission('ROLE_READ_TDOC');
    }

    /**
     * Configure les filtres disponibles dans la liste.
     *
     * @param Filters $filters
     * @return Filters
     */
    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(TextFilter::new('code'))
            ->add(TextFilter::new('nom'))
            ->add(DateTimeFilter::new('creeLe','Date Création'));
    }

    /**
     * Configure les actions disponibles dans l'interface.
     *
     * @param Actions $actions
     * @return Actions
     */
    public function configureActions(Actions $actions): Actions
    {
        return parent::configureActions($actions)
            ->disable(Action::BATCH_DELETE)
            ->add(Crud::PAGE_NEW, Action::INDEX);
    }

    /**
     * Configure les champs affichés selon la page (index, détail, édition, création).
     *
     * @param string $pageName
     * @return iterable
     */
    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->onlyOnDetail(),
            TextField::new('code'),
            TextField::new('nom'),
            DateTimeField::new('creeLe')->onlyOnDetail(),
            DateTimeField::new('editeLe')->onlyOnDetail()
        ];
    }

    /**
     * Affiche la liste des types de document.
     * Vérifie la permission ROLE_READ_TDOC.
     *
     * @param AdminContext $context
     * @return Response
     */
    public function index(AdminContext $context)
    {
        $this->denyAccessUnlessGranted('ROLE_READ_TDOC');
        return parent::index($context);
    }

    /**
     * Affiche le formulaire de création d'un type de document.
     * Vérifie la permission ROLE_CREA_TDOC.
     *
     * @param AdminContext $context
     * @return Response
     */
    public function new(AdminContext $context)
    {
        $this->denyAccessUnlessGranted('ROLE_CREA_TDOC');
        return parent::new($context);
    }

    /**
     * Affiche le détail d'un type de document.
     * Vérifie la permission ROLE_READ_TDOC.
     *
     * @param AdminContext $context
     * @return Response
     */
    public function detail(AdminContext $context)
    {
        $this->denyAccessUnlessGranted('ROLE_READ_TDOC');
        return parent::detail($context);
    }

    /**
     * Affiche le formulaire d'édition d'un type de document.
     * Vérifie la permission ROLE_EDIT_TDOC.
     *
     * @param AdminContext $context
     * @return Response
     */
    public function edit(AdminContext $context)
    {
        $this->denyAccessUnlessGranted('ROLE_EDIT_TDOC');
        return parent::edit($context);
    }

    /**
     * Supprime un type de document.
     * Vérifie la permission ROLE_DROP_TDOC.
     *
     * @param AdminContext $context
     * @return Response
     */
    public function delete(AdminContext $context)
    {
        $this->denyAccessUnlessGranted('ROLE_DROP_TDOC');
        return parent::delete($context);
    }
}
