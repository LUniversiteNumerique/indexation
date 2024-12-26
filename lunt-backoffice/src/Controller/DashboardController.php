<?php

namespace App\Controller;

use App\Repository\{NoticeRepository, UserRepository};
use App\Form\{ChangePassType,UserType};
use App\Entity\{Auteur, Etablissement, Groupe, IndexingConfig, Keyword, Univerique, User};
use EasyCorp\Bundle\EasyAdminBundle\Config\{Crud, Dashboard, MenuItem, UserMenu};
use EasyCorp\Bundle\EasyAdminBundle\{Context\AdminContext,Controller\AbstractDashboardController,Router\AdminUrlGenerator};
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\{Request, Response};
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
        return Dashboard::new()->setFaviconPath('/uploads/favicon.ico')->setTitle('<img src="/uploads/logo-UN.svg" alt="logo"> UNT contribution'); //Indexation de  <span class="text-small">UNoTice.</span>
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
            MenuItem::linkToRoute('Profil', 'fa fa-id-card', 'app_profile'),
            //MenuItem::linkToRoute('Paramètres', 'fa fa-user-cog', 'app_profile_edit'), //CU2-06: use profile > edit for the same result.
        ]);
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkToDashboard('Tableaux de bord', 'fa fa-dashboard')->setPermission('ROLE_READ_NOTI');

        yield MenuItem::linkToCrud('Les Auteurs', 'fa fa-users', Auteur::class)->setPermission('ROLE_READ_ACTE');
        yield MenuItem::section('Configurations')->setPermission('ROLE_VALI_NOTI');
        yield MenuItem::linkToCrud('Indexations', 'fa fa-book', IndexingConfig::class)->setPermission('ROLE_READ_CORE');
        yield MenuItem::linkToCrud("Utilisateurs", 'fa fa-users-gear', User::class)->setPermission('ROLE_READ_USER');
        yield MenuItem::linkToCrud("Groupes d'utilisateurs", 'fa fa-user-tag', Groupe::class)->setPermission('ROLE_READ_GROU');
        yield MenuItem::linkToCrud('Etablissement', 'fa fa-building', Etablissement::class)->setPermission('ROLE_READ_ETAB');
        yield MenuItem::linkToCrud('Mots clés', 'fa fa-tags', Keyword::class)->setPermission('ROLE_READ_KEYW');
        yield MenuItem::linkToCrud("UNT", 'fa fa-university', Univerique::class)->setPermission('ROLE_READ_UNIV');
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
}
