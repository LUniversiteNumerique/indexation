<?php

namespace App\Controller;

use App\Entity\Etablissement;
use EasyCorp\Bundle\EasyAdminBundle\Config\{Action, Actions, Crud, Filters};
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\{DateTimeField, IdField, ImageField, TextField};
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints\Image;
use function Symfony\Component\Translation\t;

/**
 * Contrôleur CRUD pour l'entité Etablissement.
 */
class EtablissementCrudController extends AbstractCrudController
{
    /**
     * Retourne le FQCN de l'entité gérée.
     *
     * @return string
     */
    public static function getEntityFqcn(): string
    {
        return Etablissement::class;
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
            ->setSearchFields(['nom', 'code'])
            ->setDefaultSort(['nom' => 'ASC'])
            ->setEntityLabelInPlural('Établissements')
            ->setEntityLabelInSingular('établissement')
            ->setEntityPermission('ROLE_READ_ETAB');
    }

    /**
     * Configure les filtres disponibles.
     *
     * @param Filters $filters
     * @return Filters
     */
    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(TextFilter::new('code', 'Nom abrégé'))
            ->add(TextFilter::new('nom', 'Etablissement'))
            ->add(DateTimeFilter::new('creeLe', 'Date Création'));
    }

    /**
     * Configure les actions disponibles et leurs permissions.
     *
     * @param Actions $actions
     * @return Actions
     */
    public function configureActions(Actions $actions): Actions
    {
        return parent::configureActions($actions)
            ->setPermission(Action::DETAIL, 'ROLE_READ_ETAB')
            ->setPermission(Action::INDEX, 'ROLE_READ_ETAB')
            ->setPermission(Action::NEW, 'ROLE_CREA_ETAB')
            ->setPermission(Action::EDIT, 'ROLE_EDIT_ETAB')
            ->setPermission(Action::DELETE, 'ROLE_DROP_ETAB')
            ->setPermission(Action::BATCH_DELETE, 'ROLE_DROP_ETAB')
            ->add(Crud::PAGE_NEW, Action::INDEX)
            ->remove(Crud::PAGE_NEW, Action::SAVE_AND_ADD_ANOTHER);
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
            TextField::new('code', 'Nom abrégé'),
            TextField::new('nom', 'Intitulé'),
            ImageField::new('logo')
                ->setUploadDir('public/uploads/logos')
                ->setBasePath('/uploads/logos')
                ->setUploadedFileNamePattern('[timestamp]-[slug].[extension]')
                ->setSortable(false)
                ->setFileConstraints([new Image(maxWidth: 500, maxHeight: 500)])
                ->setHelp(t('notice.logo_help', domain: 'EasyAdminBundle')),
            DateTimeField::new('creeLe')
                ->onlyOnDetail(),
            DateTimeField::new('editeLe')
                ->onlyOnDetail(),
        ];
    }

    /**
     * Affiche la liste des établissements.
     *
     * @param AdminContext $context
     * @return Response
     */
    public function index(AdminContext $context)
    {
        $this->denyAccessUnlessGranted('ROLE_READ_ETAB');
        return parent::index($context);
    }

    /**
     * Crée un nouvel établissement.
     *
     * @param AdminContext $context
     * @return Response
     */
    public function new(AdminContext $context)
    {
        $this->denyAccessUnlessGranted('ROLE_CREA_ETAB');
        return parent::new($context);
    }

    /**
     * Affiche le détail d'un établissement.
     *
     * @param AdminContext $context
     * @return Response
     */
    public function detail(AdminContext $context)
    {
        $this->denyAccessUnlessGranted('ROLE_READ_ETAB');
        return parent::detail($context);
    }

    /**
     * Modifie un établissement.
     *
     * @param AdminContext $context
     * @return Response
     */
    public function edit(AdminContext $context)
    {
        $this->denyAccessUnlessGranted('ROLE_EDIT_ETAB');
        return parent::edit($context);
    }

    /**
     * Supprime un établissement.
     *
     * @param AdminContext $context
     * @return Response
     */
    public function delete(AdminContext $context)
    {
        $this->denyAccessUnlessGranted('ROLE_DROP_ETAB');
        return parent::delete($context);
    }
}
