<?php

namespace App\Controller;

use App\Entity\Keyword;
use App\Repository\KeywordRepository;
use EasyCorp\Bundle\EasyAdminBundle\Config\{Action, Actions, Crud, Filters};
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Field\{BooleanField, DateTimeField, IdField, TextField};
use EasyCorp\Bundle\EasyAdminBundle\Filter\{BooleanFilter,DateTimeFilter};
use JMS\Serializer\SerializerInterface;
use Symfony\Component\HttpFoundation\{JsonResponse, Request, Response};
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Contrôleur CRUD pour l'entité Keyword dans EasyAdmin.
 */
class KeywordCrudController extends AbstractCrudController
{
    /**
     * Retourne le FQCN de l'entité gérée.
     *
     * @return string
     */
    public static function getEntityFqcn(): string
    {
        return Keyword::class;
    }

    /**
     * Configure les filtres disponibles dans EasyAdmin.
     *
     * @param Filters $filters
     * @return Filters
     */
    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(BooleanFilter::new('valide'))
            ->add(DateTimeFilter::new('creeLe', 'Date Création'));
    }

    /**
     * Configure les options CRUD (libellés, permissions, tri, etc.).
     *
     * @param Crud $crud
     * @return Crud
     */
    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('mot-clé')
            ->setEntityLabelInPlural('Mots clés')
            ->setSearchFields(['nom'])
            ->setDefaultSort(['nom' => 'ASC'])
            ->setEntityPermission('ROLE_READ_KEYW');
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
            ->disable(Action::DETAIL)
            ->add(Crud::PAGE_NEW, Action::INDEX)
            ->setPermission(Action::NEW, 'ROLE_CREA_KEYW')
            ->setPermission(Action::EDIT, 'ROLE_EDIT_KEYW')
            ->setPermission(Action::DELETE, 'ROLE_DROP_KEYW')
            ->setPermission(Action::DETAIL, 'ROLE_READ_KEYN')
            ->setPermission(Action::INDEX, 'ROLE_READ_KEYW')
            ->setPermission(Action::BATCH_DELETE, 'ROLE_DROP_KEYW');
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
            TextField::new('nom', 'Terme'),
            BooleanField::new('valide', 'Statut de validation')
                ->setSortable(false)
                ->renderAsSwitch($this->isGranted('ROLE_EDIT_KEYW')),
            DateTimeField::new('creeLe', 'Date de création')
                ->hideOnForm(),
            DateTimeField::new('editeLe', 'Dernière modification')
                ->onlyOnDetail(),
        ];
    }

    /**
     * Affiche la liste des mots-clés.
     *
     * @param AdminContext $context
     * @return Response
     */
    public function index(AdminContext $context)
    {
        $this->denyAccessUnlessGranted('ROLE_READ_KEYW');
        return parent::index($context);
    }

    /**
     * Crée un nouveau mot-clé.
     *
     * @param AdminContext $context
     * @return Response
     */
    public function new(AdminContext $context)
    {
        $this->denyAccessUnlessGranted('ROLE_CREA_KEYW');
        return parent::new($context);
    }

    /**
     * Affiche le détail d'un mot-clé.
     *
     * @param AdminContext $context
     * @return Response
     */
    public function detail(AdminContext $context)
    {
        $this->denyAccessUnlessGranted('ROLE_READ_KEYW');
        return parent::detail($context);
    }

    /**
     * Modifie un mot-clé.
     *
     * @param AdminContext $context
     * @return Response
     */
    public function edit(AdminContext $context)
    {
        $this->denyAccessUnlessGranted('ROLE_EDIT_KEYW');
        return parent::edit($context);
    }

    /**
     * Supprime un mot-clé.
     *
     * @param AdminContext $context
     * @return Response
     */
    public function delete(AdminContext $context)
    {
        $this->denyAccessUnlessGranted('ROLE_DROP_KEYW');
        return parent::delete($context);
    }

    /**
     * Crée un mot-clé via une requête AJAX.
     *
     * @param Request $request
     * @param KeywordRepository $repository
     * @param SerializerInterface $serializer
     * @return Response
     */
    #[Route('/api/keywords', name: 'app_keyword_new', methods: ['POST'])]
    #[IsGranted('ROLE_CREA_NOTI')]
    public function ajaxNew(
        Request $request,
        KeywordRepository $repository,
        SerializerInterface $serializer
    ): Response {
        $word = $request->getContent();
        $keyw = $repository->findOneBy(['nom' => $word]);

        if (!$keyw) {
            $keyw = new Keyword($word);
            $repository->add($keyw);
        }

        return new JsonResponse($serializer->serialize($keyw, 'json'), json: true);
    }
}
