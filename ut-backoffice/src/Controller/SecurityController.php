<?php

namespace App\Controller;

use App\Repository\KeywordRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{JsonResponse,Request,Response};
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class SecurityController extends AbstractController
{
    #[Route(path: '/login', name: 'app_login')]
    public function login(AuthenticationUtils $authUtils): Response
    {
        return $this->render('@EasyAdmin/page/login.html.twig', [
            'last_username' => $authUtils->getLastUsername(),
            'error' => $authUtils->getLastAuthenticationError(),

            //'favicon_path' => '/favicon-admin.svg',
            'page_title' => '<h1>UNoTice</h1>',
            'csrf_token_intention' => 'authenticate',
            'target_path' => $this->generateUrl('app_home'),

            'username_label' => 'Votre identifiant',
            'password_label' => 'Votre mot de passe',
            'sign_in_label' => 'Connexion',

            'forgot_password_enabled' => true,
            //'forgot_password_path' => $this->generateUrl('...', ['...' => '...']),
            //'forgot_password_label' => 'Forgot your password?',
        ]);
    }

    #[Route(path: '/logout', name: 'app_logout')]
    public function logout(): void
    {
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }

    #[Route(path: '/tag', name: 'app_tags', methods: ['GET'])]
    public function tags(Request $request, KeywordRepository $repository): JsonResponse
    {
        $q = $request->query->get('query'); //if ($request->query->get('p')) $tags = $repository->findUnusedTags(Notice::class);

        return $this->json(array('results'=>$repository->search($q)));
    }
}
