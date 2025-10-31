<?php

namespace App\Controller;

use App\Entity\Niveau;
use EasyCorp\Bundle\EasyAdminBundle\Config\{Action,Actions,Crud,Filters};
use App\Repository\NiveauRepository;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\{DateTimeField, IdField, IntegerField, TextField};
use EasyCorp\Bundle\EasyAdminBundle\Filter\{DateTimeFilter,TextFilter};
use Symfony\Component\HttpFoundation\Response;

/**
 * Contrôleur CRUD pour l'entité Niveau dans EasyAdmin.
 *
 * Gère la configuration des champs, filtres, actions et permissions
 * pour l'administration des niveaux de public cible.
 */
class NiveauCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly NiveauRepository $repository
    ) {}
    /**
     * Retourne le FQCN (nom de classe complet) de l'entité Niveau.
     *
     * @return string
     */
    public static function getEntityFqcn(): string
    {
        return Niveau::class;
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
            ->setDefaultSort(['ordre' => 'ASC'])
            ->setEntityLabelInPlural("Publics cibles")
            ->setEntityLabelInSingular("public cible")
            ->setEntityPermission('ROLE_READ_NIVE');
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
            IntegerField::new('ordre', 'Ordre d\'affichage'),
            DateTimeField::new('creeLe')->onlyOnDetail(),
            DateTimeField::new('editeLe')->onlyOnDetail()
        ];
    }

    /**
     * Crée une nouvelle instance de Niveau avec l'ordre prérempli.
     *
     * @param string $entityFqcn Le FQCN de l'entité.
     * @return Niveau
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    public function createEntity(string $entityFqcn)
    {
        $maxOrdre = $this->repository
            ->createQueryBuilder('n')
            ->select('MAX(n.ordre)')
            ->getQuery()
            ->getSingleScalarResult();

        $niveau = new Niveau();
        $niveau->setOrdre(((int) $maxOrdre) + 1);

        return $niveau;
    }

    /**
     * Affiche la liste des niveaux.
     * Vérifie la permission ROLE_READ_NIVE.
     *
     * @param AdminContext $context
     * @return Response
     */
    public function index(AdminContext $context)
    {
        $this->denyAccessUnlessGranted('ROLE_READ_NIVE');
        return parent::index($context);
    }

    /**
     * Affiche le formulaire de création d'un niveau.
     * Vérifie la permission ROLE_CREA_NIVE.
     *
     * @param AdminContext $context
     * @return Response
     */
    public function new(AdminContext $context)
    {
        $this->denyAccessUnlessGranted('ROLE_CREA_NIVE');
        return parent::new($context);
    }

    /**
     * Affiche le détail d'un niveau.
     * Vérifie la permission ROLE_READ_NIVE.
     *
     * @param AdminContext $context
     * @return Response
     */
    public function detail(AdminContext $context)
    {
        $this->denyAccessUnlessGranted('ROLE_READ_NIVE');
        return parent::detail($context);
    }

    /**
     * Affiche le formulaire d'édition d'un niveau.
     * Vérifie la permission ROLE_EDIT_NIVE.
     *
     * @param AdminContext $context
     * @return Response
     */
    public function edit(AdminContext $context)
    {
        $this->denyAccessUnlessGranted('ROLE_EDIT_NIVE');
        return parent::edit($context);
    }

    /**
     * Supprime un niveau.
     * Vérifie la permission ROLE_DROP_NIVE.
     *
     * @param AdminContext $context
     * @return Response
     */
    public function delete(AdminContext $context)
    {
        $this->denyAccessUnlessGranted('ROLE_DROP_NIVE');
        return parent::delete($context);
    }
}
