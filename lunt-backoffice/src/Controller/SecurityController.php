<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\ChangePassType;
use App\Repository\UserRepository;
use App\Service\MailerService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Request,Response};
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Csrf\TokenGenerator\TokenGeneratorInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class SecurityController extends AbstractController
{
    public function __construct(private readonly UserRepository $repository) {}

    #[Route(path: '/login', name: 'app_login')]
    public function login(AuthenticationUtils $authUtils): Response
    {
        return $this->render('@EasyAdmin/page/login.html.twig', [
            'last_username' => $authUtils->getLastUsername(),
            'error' => $authUtils->getLastAuthenticationError(),

            'favicon_path' => 'images/favicon.ico',
            'page_title' => '<img src="images/logo-1000px.png" alt="logo">',
            'csrf_token_intention' => 'authenticate',
            'target_path' => $this->generateUrl('app_home'),

            'username_label' => 'Votre identifiant',
            'password_label' => 'Votre mot de passe',
            'sign_in_label' => 'Connexion',

            'forgot_password_enabled' => true,
            'forgot_password_path' => $this->generateUrl('app_reset_request'),
        ]);
    }

    #[Route("/reset-pass", name: 'app_reset_request')]
    public function request(Request $request, MailerService $mailer, TokenGeneratorInterface $generator): Response
    {
        if($email = $request->get('email')) {
            /** @var User $user */
            $user = $this->repository->findOneBy(['email' => $email]);
            if ($user) {
                $resetoken = $generator->generateToken();
                $url = $this->generateUrl('app_reset_response', ['token' => $resetoken], UrlGeneratorInterface::ABSOLUTE_URL);
                $this->repository->add($user->setReseToken($resetoken));

                $mailer->sendEmail($user->getEmail(), 'Réinitialiser votre mot de passe UNT',
                    "Bonjour " . $user->getName() . '<br/>Vous avez demandé à réinitialiser le mot de passe de votre espace UNT.<br/><br/>Merci de bien vouloir cliquer sur le lien suivant pour <a href="' . $url . '">mettre à jour votre mot de passe</a>.',
                );
                $this->addFlash('notice', 'Vous allez recevoir dans quelques secondes un mail avec la procédure pour réinitialiser votre mot de passe.');
            } else $this->addFlash('notice', 'Cette adresse email est inconnue.');
        }
        return $this->render('security/reset_req.html.twig');
    }

    #[Route("/reset-pass/{token}", name: "app_reset_response")]
    public function response(Request $request, UserPasswordHasherInterface $encoder): Response
    {
        /** @var User $user */
        $user = $this->repository->findOneBy(['reseToken' => $request->get('token')]);
        if (!$user) {
            $this->addFlash('notice', 'Votre demande de mot de passe a expiré.');
            return $this->redirectToRoute('app_reset_request');
        }

        $form = $this->createForm(ChangePassType::class,$user,['user_logged' => false]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $hashNewPass = $encoder->hashPassword($user, $form->get('newPassword')->getData());

            $this->repository->add($user->setPassword($hashNewPass)->setReseToken(null)->setEnabled(true));

            $this->addFlash('notice', 'Votre mot de passe a bien été mis à jour.');
            return $this->redirectToRoute('app_login');
        }

        return $this->render('security/reset_res.html.twig', ['form' => $form]);
    }
}
