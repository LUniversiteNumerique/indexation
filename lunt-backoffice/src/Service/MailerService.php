<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\{MailerInterface, Exception\TransportExceptionInterface};
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mime\Email;

/**
 * Service d'envoi d'e-mails.
 */
readonly class MailerService
{
    /**
     * @param string $untSenderEmail Adresse e-mail de l'expéditeur par défaut
     * @param MailerInterface $mailer Service de mail Symfony
     * @param LoggerInterface $untLogger Logger pour la journalisation des erreurs
     */
    public function __construct(
        #[Autowire('%unt_sender_email%')]
        private string $untSenderEmail,
        private MailerInterface $mailer,
        private LoggerInterface $untLogger,
    ) {}

    /**
     * Envoie un e-mail texte simple.
     *
     * @param string|string[] $to Destinataire(s)
     * @param string $subject Sujet du mail
     * @param string $content Contenu du mail
     * @param string|null $from Expéditeur (optionnel)
     */
    public function sendEmail(string|array $to, string $subject, string $content, ?string $from = null): void
    {
        $from ??= $this->untSenderEmail;
        $email = (new Email())
            ->from($from)
            ->to($to)
            ->subject($subject)
            ->text($content);

        try {
            $this->mailer->send($email);
        } catch (TransportExceptionInterface $e) {
            $this->untLogger->error(
                sprintf("Erreur d'envoi de mail à <<%s>>", is_array($to) ? implode(', ', $to) : $to),
                [
                    'from' => $from,
                    'exception' => $e->getMessage(),
                ]
            );
        }
    }

    /**
     * Envoie un e-mail basé sur un template Twig.
     *
     * @param string|string[] $to Destinataire(s)
     * @param string $subject Sujet du mail
     * @param string $template Nom du template Twig
     * @param array $variables Variables à injecter dans le template
     * @param string|null $from Expéditeur (optionnel)
     */
    public function sendTwig(string|array $to, string $subject, string $template, array $variables = [], ?string $from = null): void
    {
        $from ??= $this->untSenderEmail;
        $email = (new TemplatedEmail())
            ->from($from)
            ->to($to)
            ->subject($subject)
            ->htmlTemplate($template);

        if (!empty($variables)) {
            $email->context($variables);
        }

        try {
            $this->mailer->send($email);
        } catch (TransportExceptionInterface $e) {
            $this->untLogger->error(
                sprintf("Erreur d'envoi de mail à <<%s>>", is_array($to) ? implode(', ', $to) : $to),
                [
                    'from' => $from,
                    'exception' => $e->getMessage(),
                ]
            );
        }
    }
}