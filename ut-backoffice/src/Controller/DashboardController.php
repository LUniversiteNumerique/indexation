<?php

namespace App\Controller;

use App\Form\{ChangePassType,UserType};
use App\Repository\UserRepository;
use App\Entity\{Auteur, Discipline, Etablissement, Groupe, Keyword, User};
use EasyCorp\Bundle\EasyAdminBundle\Config\{Action, Assets, Crud, Dashboard, MenuItem, UserMenu};
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Psr\Container\{ContainerExceptionInterface,NotFoundExceptionInterface};
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\{Request,Response};
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

class DashboardController extends AbstractDashboardController
{
    public function __construct(private readonly UserRepository $repository) {}

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()->setFaviconPath('images/favicon.ico')
            ->setTitle('<img src="images/logo-UN.svg" alt="logo"> Indexation de  <span class="text-small">UNoTice.</span>')
             //->renderSidebarMinimized()
        ;
    }

    public function configureAssets(): Assets
    {
        return Assets::new()->addCssFile('build/admin.css');
    }

    public function configureCrud(): Crud
    {
        return Crud::new()->showEntityActionsInlined()->setDateTimeFormat('medium', 'short');
    }

    public function configureUserMenu(UserInterface $user): UserMenu
    {
        $me = parent::configureUserMenu($user);
        $appProfile = Action::new('appProfile', 'Profile', 'fa fa-id-card')
            ->linkToCrudAction('profile');

        if($user instanceof User) $me->setName($user->getName());//if($user->getAvatar()) $me->setAvatarUrl('/uploads/'.$user->getAvatar());
        $me->addMenuItems([
            MenuItem::linkToRoute('My Profile', 'fa fa-id-card', 'app_profile'),
            MenuItem::linkToRoute('Settings', 'fa fa-user-cog', 'app_profile_edit'),
        ]);

        return $me;
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkToDashboard('Dashboard', 'fa fa-dashboard'); //yield MenuItem::linkToCrud('Notices', 'fa fa-table', Notice::class);

        if ($this->isGranted('ROLE_DOCUM')) { //comments,folder
            yield MenuItem::section('Configurations');
            yield MenuItem::linkToCrud('Les Auteurs', 'fa fa-users', Auteur::class);
            yield MenuItem::linkToCrud('Etablissement', 'fa fa-university', Etablissement::class);
            yield MenuItem::linkToCrud('Discipline', 'fa fa-book', Discipline::class);
            yield MenuItem::linkToCrud('Mots clés', 'fa fa-tags', Keyword::class);
        }
        if ($this->isGranted('ROLE_ADMIN')) {
            yield MenuItem::linkToCrud("L'annuaire", 'fa fa-user-group', User::class);
            yield MenuItem::linkToCrud("Groupe d'utilisateurs", 'fa fa-cog', Groupe::class);
        }
    }

    /**
     * @throws NotFoundExceptionInterface
     * @throws ContainerExceptionInterface
     */
    #[Route('/', name: 'app_home'),]
    public function index(): Response
    {
        $br = $this->container->get(AdminUrlGenerator::class);
        return $this->isGranted("ROLE_CONTR") ?
            $this->redirect($br->setController(NoticeCrudController::class)->generateUrl()):
            $this->render('admin/index.html.twig', ['userStats' => []]);
    }

    #[Route('/profile', name: 'app_profile')]
    public function profile(#[CurrentUser] ?User $user): Response
    {
        if (!$user)  return $this->redirectToRoute('app_login');
        return $this->render('admin/profile.html.twig');
    }

    #[Route('/profile/edit-profile', name: 'app_profile_edit', methods: ['GET', 'POST'])]
    public function editProfile(AdminContext $ctx, Request $request): Response
    {
        $user = $ctx->getUser();
        $form = $this->createForm(UserType::class, $user, ['owner' => true]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->repository->add();

            $this->addFlash('success', 'user.updated_successfully');

            return $this->redirectToRoute('app_profile', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('admin/edit.html.twig', [
            'user' => $user,
            'form' => $form,
        ]);
    }

    #[Route('/profile/change-pass', name: 'app_profile_change', methods: ['GET', 'POST'])]
    public function changePass(Request $request, Security $security): Response
    {
        $user = $this->getUser();
        $form = $this->createForm(ChangePassType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->repository->add();

            return $security->logout(validateCsrfToken: false) ?? $this->redirectToRoute('app_home');
        }

        return $this->render('admin/passchange.html.twig', ['form' => $form,]);
    }
}
