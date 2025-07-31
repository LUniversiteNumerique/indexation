<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\{MailerInterface,Exception\TransportExceptionInterface};
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mime\Email;

readonly class MailerService
{
    public function __construct(
        #[Autowire('%unt_sender_email%')]
        private string $untSenderEmail,
        private MailerInterface $mailer,
        private LoggerInterface $untLogger,
    ) {}

    public function sendEmail($to, string $subject, string $content, $from=null): void
    {
        $from ??= $this->untSenderEmail;
        $email = (new Email())
            ->from($from)->to($to)
            ->subject($subject)->text($content);

        try { $this->mailer->send($email); }
        catch (TransportExceptionInterface $e) {
            $this->untLogger->error(sprintf("Erreur d'envoi de mail au <<%s>>",$to), [
                'from' => $from, 'exception' => $e->getMessage(),
            ]);
        }
    }

    public function sendTwig($to, string $subject, string $content, array $variables = [], $from = null): void
    {
        $from ??= $this->untSenderEmail;
        $email = (new TemplatedEmail())
            ->from($from)->to($to)->subject($subject)
            ->htmlTemplate($content);

        if(!empty($variables)) $email->context($variables);

        try { $this->mailer->send($email); }
        catch (TransportExceptionInterface $e) {
            $this->untLogger->error(sprintf("Erreur d'envoi de mail au <<%s>>",$to), [
                'from' => $from, 'exception' => $e->getMessage(),
            ]);
        }
    }

}