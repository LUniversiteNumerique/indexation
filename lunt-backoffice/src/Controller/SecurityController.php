<?php

namespace App\Controller;

use App\Form\ChangePassType;
use App\Repository\UserRepository;
use App\Service\MailerService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Request,Response};
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\{Attribute\Route,Generator\UrlGeneratorInterface};
use Symfony\Component\Security\Csrf\TokenGenerator\TokenGeneratorInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class SecurityController extends AbstractController
{
    const VALIDATIME_TOKEN = '+10 min';

    public function __construct(private readonly UserRepository $repository) {}

    #[Route(path: '/login', name: 'app_login')]
    public function login(AuthenticationUtils $authUtils): Response
    {
        return $this->render('@EasyAdmin/page/login.html.twig', [
            'last_username' => $authUtils->getLastUsername(),
            'error' => $authUtils->getLastAuthenticationError(),

            'favicon_path' => 'uploads/favicon.ico',
            'page_title' => '<img src="uploads/logo-1000px.png" alt="logo">',
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
            //** @var User $user */
            $user = $this->repository->findOneBy(['email' => $email]);
            if ($user) {
                $resetoken = $generator->generateToken(); $user->setReseToken($resetoken);
                $url = $this->generateUrl('app_reset_response', ['token' => $resetoken], UrlGeneratorInterface::ABSOLUTE_URL);
                $this->repository->add($user->setTokenExpiresAt(new \DateTimeImmutable(self::VALIDATIME_TOKEN)));

                $mailer->sendEmail($user->getEmail(), 'Réinitialiser votre mot de passe UNT',
                    "Bonjour " . $user->getName() . '<br/>Vous avez demandé à réinitialiser le mot de passe de votre espace UNT.<br/><br/>Merci de bien vouloir cliquer sur le lien suivant pour <a href="' . $url . '">mettre à jour votre mot de passe</a>.',
                );
                $this->addFlash('success', 'Vous recevez dans quelques instants un mail avec la procédure de réinitialisation.');
            } else $this->addFlash('danger', 'Cette adresse email est inconnue.');
        }
        return $this->render('security/reset_req.html.twig');
    }

    #[Route("/reset-pass/{token}", name: "app_reset_response")]
    public function response(Request $request, UserPasswordHasherInterface $encoder): Response
    {
        //** @var User $user */
        $user = $this->repository->findOneBy(['reseToken' => $request->get('token')]);
        if (!$user || $user->getTokenExpiresAt() < new \DateTimeImmutable()) { //->getTimestamp() <= time()
            $this->addFlash('danger', 'Votre demande de mot de passe a expiré.');
            return $this->redirectToRoute('app_reset_request');
        }

        $form = $this->createForm(ChangePassType::class,$user,['user_logged' => false]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $hashNewPass = $encoder->hashPassword($user, $form->get('newPassword')->getData());
            $user->setReseToken(null)->setPassword($hashNewPass)->setTokenExpiresAt(null);
            $this->repository->add($user->setEnabled(true));

            $this->addFlash('notice', 'Votre mot de passe a bien été mis à jour.');
            return $this->redirectToRoute('app_login');
        }

        return $this->render('security/reset_res.html.twig', ['form' => $form]);
    }
}
