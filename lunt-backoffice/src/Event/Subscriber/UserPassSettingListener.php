<?php

namespace App\Event\Subscriber;

use App\Event\UserPassSettingEvent;
use App\Service\MailerService;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[AsEventListener]
final readonly class UserPassSettingListener
{
    public function __construct(
        private MailerService         $mailer,
        private UrlGeneratorInterface $generator
    ){}

    public function __invoke(UserPassSettingEvent $event): void
    {
        $user = $event->user;
        $type = $event->type;

        $url = $this->generator->generate(
            'app_reset_response',
            ['token' => $user->getReseToken()],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        $this->mailer->sendTwig($user->getEmail(),
                sprintf('[UNT] %s', $type ? 'Création de votre espace':'Réinitialiser votre mot de passe'),
            'emails/usetting.html.twig', ['user' => $user, 'url' => $url, 'type' => $type]
        );
    }
}