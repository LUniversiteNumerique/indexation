<?php

namespace App\Event\Subscriber;

use App\Event\UserPassSettingEvent;
use App\Service\MailerService;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Écouteur d'événement pour la configuration ou la réinitialisation du mot de passe utilisateur.
 */
#[AsEventListener]
final readonly class UserPassSettingListener
{
    /**
     * @param MailerService $mailer Service d'envoi d'e-mails
     * @param UrlGeneratorInterface $generator Générateur d'URL
     */
    public function __construct(
        private MailerService $mailer,
        private UrlGeneratorInterface $generator
    ) {}

    /**
     * Gère l'événement de configuration ou de réinitialisation du mot de passe utilisateur.
     *
     * @param UserPassSettingEvent $event
     */
    public function __invoke(UserPassSettingEvent $event): void
    {
        $user = $event->user;
        $isCreation = $event->type;

        $url = $this->generator->generate(
            'app_reset_response',
            ['token' => $user->getReseToken()],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        $subject = sprintf(
            '[UNT] %s',
            $isCreation ? 'Création de votre espace' : 'Réinitialiser votre mot de passe'
        );

        $this->mailer->sendTwig(
            $user->getEmail(),
            $subject,
            'emails/usetting.html.twig',
            [
                'user' => $user,
                'url' => $url,
                'type' => $isCreation
            ]
        );
    }
}