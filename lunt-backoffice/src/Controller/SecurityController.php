<?php

namespace App\Controller;

use App\Entity\User;
use App\Event\UserPassSettingEvent;
use App\Form\ChangePassType;
use App\Repository\UserRepository;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Request,Response};
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\TokenGenerator\TokenGeneratorInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class SecurityController extends AbstractController
{
    public function __construct(
        private readonly UserRepository $repository,
        private readonly EventDispatcherInterface $dispatcher,
    ) {}

    #[Route(path: '/login', name: 'app_login')]
    public function login(AuthenticationUtils $authUtils): Response
    {
        return $this->render('security/login.html.twig', [
            'last_username' => $authUtils->getLastUsername(),
            'error' => $authUtils->getLastAuthenticationError(),

            'favicon_path' => 'uploads/favicon.ico',
            'page_title' => '<img src="uploads/logo-1000px.png" alt="logo"> UNT contribution',
            'csrf_token_intention' => 'authenticate',

            'forgot_password_enabled' => true,
            'forgot_password_path' => $this->generateUrl('app_reset_request'),
        ]);
    }

    #[Route("/reset-pass", name: 'app_reset_request')]
    public function request(Request $request, TokenGeneratorInterface $generator): Response
    {
        if($email = $request->get('email')) {
            /** @var User $user */ $user = $this->repository->findOneBy(['email' => $email]);
            if ($user) {
                $this->repository->add($user->setReseToken($generator->generateToken())
                    ->setTokenExpiresAt(new \DateTimeImmutable(User::VALIDATIME_TOKEN.' min')));
                $this->dispatcher->dispatch(new UserPassSettingEvent($user));

                $this->addFlash('success', 'Vous recevez dans quelques instants un mail avec la procédure de réinitialisation.');
            } else $this->addFlash('danger', 'Cette adresse email est inconnue.');
        }
        return $this->render('security/reset_req.html.twig');
    }

    #[Route("/reset-pass/{token}", name: "app_reset_response")]
    public function response(Request $request, UserPasswordHasherInterface $encoder): Response
    {
        /** @var User $user */
        $user = $this->repository->findOneBy(['reseToken' => $request->get('token')]);
        if (!$user || $user->getTokenExpiresAt() < new \DateTimeImmutable()) {
            $this->addFlash('danger', 'Votre demande de mot de passe a expiré.');
            return $this->redirectToRoute('app_reset_request');
        }

        $form = $this->createForm(ChangePassType::class,$user,['user_logged' => false]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $hashNewPass = $encoder->hashPassword($user, $form->get('newPassword')->getData());
            $this->repository->add($user->setPassword($hashNewPass)->setReseToken(null)->setTokenExpiresAt(null)->setEnabled(true));

            $this->addFlash('success', 'Votre mot de passe a bien été mis à jour.');
            return $this->redirectToRoute('app_login');
        }

        return $this->render('security/reset_res.html.twig', ['form' => $form]);
    }
}
