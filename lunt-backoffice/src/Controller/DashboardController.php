<?php

namespace App\Controller;

use JMS\Serializer\SerializerInterface;
use App\Repository\{KeywordRepository, NoticeRepository, UserRepository};
use App\Form\{ChangePassType,UserType};
use App\Entity\{Auteur, Etablissement, Groupe, IndexingConfig, Keyword, User};
use EasyCorp\Bundle\EasyAdminBundle\Config\{Crud, Dashboard, MenuItem, UserMenu};
use EasyCorp\Bundle\EasyAdminBundle\{Context\AdminContext,Controller\AbstractDashboardController,Router\AdminUrlGenerator};
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\{JsonResponse, Request, Response};
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

class DashboardController extends AbstractDashboardController
{
    public function __construct(
        private readonly AdminUrlGenerator $generator,
        private readonly NoticeRepository $repository,
        private readonly UserRepository $userRep,
    ) {}

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()->setFaviconPath('/uploads/favicon.ico')->setTitle('<img src="/uploads/logo-UN.svg" alt="logo"> Indexation de  <span class="text-small">UNoTice.</span');
    }

    public function configureCrud(): Crud
    {
        return Crud::new()->showEntityActionsInlined()->setDateTimeFormat('medium', 'short');
    }

    public function configureUserMenu(UserInterface $user): UserMenu
    {
        $me = parent::configureUserMenu($user);
        if($user instanceof User) $me->setName($user->getName());

        return $me->addMenuItems([
            MenuItem::linkToRoute('Profile', 'fa fa-id-card', 'app_profile'),
            MenuItem::linkToRoute('Settings', 'fa fa-user-cog', 'app_profile_edit'),
        ]);
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkToDashboard('Tableau de bord', 'fa fa-dashboard');

        yield MenuItem::linkToCrud('Les Auteurs', 'fa fa-users', Auteur::class)->setPermission('ROLE_READ_ACTE');
        yield MenuItem::section('Configurations');
        yield MenuItem::linkToCrud('Etablissement', 'fa fa-university', Etablissement::class)->setPermission('ROLE_READ_ETAB');
        yield MenuItem::linkToCrud('Indexation', 'fa fa-book', IndexingConfig::class)->setPermission('ROLE_READ_CORE');
        yield MenuItem::linkToCrud('Mots clés', 'fa fa-tags', Keyword::class)->setPermission('ROLE_READ_KEYW');
        yield MenuItem::linkToCrud("Annuaire", 'fa fa-user-group', User::class)->setPermission('ROLE_READ_USER');
        yield MenuItem::linkToCrud("Groupe d'utilisateurs", 'fa fa-cog', Groupe::class)->setPermission('ROLE_READ_GROU');
    }

    #[Route('/', name: 'app_home'),]
    public function index(): Response
    {
        /** @var User $user */ $user = $this->getUser();
        return $this->isGranted("ROLE_VALI_NOTI") ?
            $this->render('admin/index.html.twig', ['noEtats' => $this->repository->countByEtat($user->getUntheme()),]):
            $this->redirect($this->generator->setController(NoticeCrudController::class)->generateUrl());
    }

    #[Route('/profile', name: 'app_profile')]
    public function profile(#[CurrentUser] ?User $user): Response
    {
        if (!$user)  return $this->redirectToRoute('app_login');
        return $this->render('admin/profile_show.html.twig');
    }

    #[Route('/profile/edit-profile', name: 'app_profile_edit', methods: ['GET', 'POST'])]
    public function editProfile(AdminContext $ctx): Response
    {
        $user = $ctx->getUser();
        $form = $this->createForm(UserType::class, $user, ['owner' => true]);
        $form->handleRequest($ctx->getRequest());

        if ($form->isSubmitted() && $form->isValid()) {
            $this->userRep->add();

            $this->addFlash('success', 'Informations mises à jour avec succès !');

            return $this->redirectToRoute('app_profile', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('admin/profile_edit.html.twig', [
            'user' => $user, 'form' => $form->createView(),
        ]);
    }

    #[Route('/profile/change-pass', name: 'app_profile_change', methods: ['GET', 'POST'])]
    public function changePass(Request $request, Security $security): Response
    {
        $user = $this->getUser();
        $form = $this->createForm(ChangePassType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->userRep->add();

            return $security->logout(validateCsrfToken: false) ?? $this->redirectToRoute('app_home');
        }

        return $this->render('security/change.html.twig', ['form' => $form->createView(),]);
    }

    #[Route(path: '/tag', name: 'app_tags', methods: ['GET'])]
    public function tags(Request $request, KeywordRepository $repository, SerializerInterface $serializer): JsonResponse
    {
        $result = $repository->search($request->query->get('query'));

        return new JsonResponse($serializer->serialize(['results' => $result],'json'), Response::HTTP_OK, [], true);
    }
}
