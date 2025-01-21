<?php

namespace App\Service;

use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\{MailerInterface,Exception\TransportExceptionInterface};
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mime\Email;

readonly class MailerService
{
    public function __construct(
        #[Autowire('%unt_sender_email%')] private string $untSenderEmail,
        private MailerInterface $mailer
    ) {}

    public function sendEmail($to, string $subject, string $content, $from=null): void
    {
        $email = (new Email())
            ->from($from ?? $this->untSenderEmail)
            ->to($to)->subject($subject)
            ->text($content);
        try { $this->mailer->send($email); }
        catch (TransportExceptionInterface $e) {}
    }

    public function sendTwig($to, string $subject, string $content, array $variables = [], $from = null): void
    {
        $email = (new TemplatedEmail())
            ->from($from ?? $this->untSenderEmail)
            ->to($to)->subject($subject)
            ->htmlTemplate($content) //->textTemplate('emails/signup.txt.twig')
        ;
        if(!empty($variables)) $email->context($variables);

        try { $this->mailer->send($email); }
        catch (TransportExceptionInterface) {}
    }

}