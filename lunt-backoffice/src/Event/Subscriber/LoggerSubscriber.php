<?php

namespace App\Event\Subscriber;

use EasyCorp\Bundle\EasyAdminBundle\Event\{AbstractLifecycleEvent, AfterEntityDeletedEvent, AfterEntityPersistedEvent, AfterEntityUpdatedEvent, BeforeEntityUpdatedEvent};
use App\Controller\NoticeCrudController;
use App\Event\{AfterNoticeAdjustingEvent, AfterNoticeApprovingEvent, AfterNoticeRejectingEvent, AfterNoticeStateSetEvent, AfterNoticeSubmissionEvent};
use App\Service\MailerService;
use DateTime;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use App\Entity\{Groupe, Notice, Univerique, User};
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\{EventSubscriberInterface, Attribute\AsEventListener};
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\UrlHelper;

/**
 * Abonné aux événements pour la gestion des logs et notifications.
 */
readonly class LoggerSubscriber implements EventSubscriberInterface
{
    /**
     * @param Security $security
     * @param MailerService $mailer
     * @param LoggerInterface $untLogger
     * @param AdminUrlGenerator $generator
     * @param EntityManagerInterface $em
     * @param UrlHelper $urlHelper
     */
    public function __construct(
        private Security $security,
        private MailerService $mailer,
        private LoggerInterface $untLogger,
        private AdminUrlGenerator $generator,
        private EntityManagerInterface $em,
        private UrlHelper $urlHelper
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            AfterNoticeSubmissionEvent::class => 'noticeSubmitting',
            AfterNoticeAdjustingEvent::class => 'noticeAdjusting',
            AfterNoticeApprovingEvent::class => 'noticeApproving',
            AfterNoticeRejectingEvent::class => 'noticeRejecting',
            AfterNoticeStateSetEvent::class => 'logChanging',
            AfterEntityDeletedEvent::class => 'logDeleting',
            AfterEntityPersistedEvent::class => 'logCreating',
            AfterEntityUpdatedEvent::class => 'logUpdating',
            BeforeEntityUpdatedEvent::class => ['onDateSetting'],
            LoginSuccessEvent::class => 'onLoginSuccess',
        ];
    }

    /**
     * Traite la soumission d'une notice.
     * @param AfterNoticeSubmissionEvent $event
     * @return void
     */
    public function noticeSubmitting(AfterNoticeSubmissionEvent $event): void
    {
        /** @var Notice $entity */
        $entity = $event->getEntityInstance();
        /** @var User $user */
        $user = $this->security->getUser();

        $disciplinegroups = $entity->getDisciplineGroups();
        $allUnts = $this->em->getRepository(Univerique::class)->findAll();
        $allUsers = $this->em->getRepository(User::class)->findAll();
        $linkedUnts = [];

        foreach ($allUnts as $unt) {
            $untFields = $unt->getFields();
            foreach ($disciplinegroups as $disciplinegroup) {
                $champDisc = $disciplinegroup->getChampDisc();
                if ($champDisc && $untFields->contains($champDisc)) {
                    $linkedUnts[$unt->getId()] = $unt;
                    break;
                }
            }
        }

        $documentalisteGroupe = $this->em->getRepository(Groupe::class)->findOneBy(['label' => 'Documentaliste']);
        $documentalisteId = $documentalisteGroupe?->getId();

        $emailsToNotify = [];
        foreach ($linkedUnts as $unt) {
            foreach ($allUsers as $userItem) {
                $userGroup = $userItem->getGroup();
                if ($userGroup?->getId() !== $documentalisteId) {
                    continue;
                }
                $userUntheme = $userItem->getUntheme();
                if ($userUntheme?->getId() !== $unt->getId() || !$userItem->isEnabled()) {
                    continue;
                }
                $userEmail = $userItem->getEmail();
                if ($userEmail) {
                    $emailsToNotify[$userEmail] = true;
                }
            }
        }

        $this->untLogger->notice(
            sprintf("%s demande de soumettre la notice %s", $user, $entity),
            [
                'actionType' => 'Soumission',
                'ressType' => Notice::class,
                'ressInstance' => $entity->getId(),
                'userInstance' => $user->getId(),
                'userGroup' => $user->getGroup()
            ]
        );

        $url = $this->generator
            ->setController(NoticeCrudController::class)
            ->setAction(Action::DETAIL)
            ->setEntityId($entity->getId())
            ->generateUrl();

        $absoluteUrl = $this->urlHelper->getAbsoluteUrl($url);

        foreach (array_keys($emailsToNotify) as $email) {
            $this->mailer->sendTwig(
                $email,
                sprintf("[UNT] Demande de Soumission de la notice %d", $entity->getId()),
                'emails/submit.html.twig',
                [
                    'user' => $user,
                    'url' => $absoluteUrl,
                    'notice' => $entity->getTitre()
                ]
            );
        }
    }

    /**
     * Traite la demande de rectification d'une notice.
     * @param AfterNoticeAdjustingEvent $event
     * @return void
     */
    public function noticeAdjusting(AfterNoticeAdjustingEvent $event): void
    {
        /** @var Notice $entity */
        $entity = $event->getEntityInstance();
        /** @var User $user */
        $user = $this->security->getUser();

        $to = $entity->getValidateur();
        $this->untLogger->notice(
            sprintf("%s demande de rectifier la notice %s", $user, $entity),
            [
                'actionType' => 'Rectification',
                'ressType' => Notice::class,
                'ressInstance' => $entity->getId(),
                'userInstance' => $user->getId(),
                'userGroup' => $user->getGroup()
            ]
        );

        $url = $this->generator
            ->setController(NoticeCrudController::class)
            ->setAction(Action::DETAIL)
            ->setEntityId($entity->getId())
            ->generateUrl();

        $absoluteUrl = $this->urlHelper->getAbsoluteUrl($url);

        $this->mailer->sendTwig(
            $to?->getEmail(),
            sprintf("[UNT] Demande de Rectification de la notice %d", $entity->getId()),
            'emails/adjust.html.twig',
            [
                'user' => $user,
                'url' => $absoluteUrl,
                'notice' => $entity->getTitre(),
            ]
        );
    }

    /**
     * Traite la validation d'une notice.
     * @param AfterNoticeApprovingEvent $event
     * @return void
     */
    public function noticeApproving(AfterNoticeApprovingEvent $event): void
    {
        /** @var Notice $entity */
        $entity = $event->getEntityInstance();
        /** @var User $user */
        $user = $this->security->getUser();

        $from = $entity->getCreateur();
        $this->untLogger->notice(
            sprintf("%s vient d'être validée par %s", $entity, $user),
            [
                'actionType'=> 'Validation',
                'ressType' => Notice::class,
                'ressInstance' => $entity->getId(),
                'userInstance' => $user->getId(),
                'userGroup' => $user->getGroup()
            ]
        );

        $url = $this->generator
            ->setController(NoticeCrudController::class)
            ->setAction(Action::DETAIL)
            ->setEntityId($entity->getId())
            ->generateUrl();

        $absoluteUrl = $this->urlHelper->getAbsoluteUrl($url);

        $this->mailer->sendTwig(
            $from->getEmail(),
            sprintf("[UNT] La notice N° %d est validée", $entity->getId()),
            'emails/approve.html.twig',
            [
                'user' => $user,
                'url' => $absoluteUrl,
                'notice' => $entity->getTitre()
            ]
        );
    }

    /**
     * Traite le rejet d'une notice.
     * @param AfterNoticeRejectingEvent $event
     * @return void
     */
    public function noticeRejecting(AfterNoticeRejectingEvent $event): void
    {
        /** @var Notice $entity */
        $entity = $event->getEntityInstance();
        /** @var User $user */
        $user = $this->security->getUser();

        $motif = $event->getMotifs();
        $from = $entity->getCreateur();
        $this->untLogger->notice(
            sprintf("%s vient d'être rejetée par %s pour <<%s>>", $entity, $user, $motif),
            [
                'actionType' => 'Rejet',
                'ressType' => Notice::class,
                'ressInstance' => $entity->getId(),
                'userInstance' => $user->getId(),
                'userGroup' => $user->getGroup()
            ]
        );

        $url = $this->generator
            ->setController(NoticeCrudController::class)
            ->setAction(Action::DETAIL)
            ->setEntityId($entity->getId())
            ->generateUrl();

        $absoluteUrl = $this->urlHelper->getAbsoluteUrl($url);

        $this->mailer->sendTwig(
            $from->getEmail(),
            sprintf("[UNT] La notice N° %d est rejetée", $entity->getId()),
            'emails/reject.html.twig',
            [
                'user' => $user,
                'url' => $absoluteUrl,
                'motifs' => $motif,
                'notice' => $entity->getTitre(),
            ]
        );
    }

    /**
     * Log le changement d'état d'une notice.
     * @param AfterNoticeStateSetEvent $event
     * @return void
     */
    public function logChanging(AfterNoticeStateSetEvent $event): void
    {
        /** @var User $user */
        $user = $this->security->getUser();
        $action = $event->getAction();
        $entity = $event->getEntityInstance();
        if (!$entity instanceof Notice) {
            return;
        }

        $this->untLogger->notice(
            sprintf("%s vient d'être <<%s>> par %s", $entity, $action[1], $user),
            [
                'actionType' => $action[2],
                'ressType' => Notice::class,
                'ressInstance' => $entity->getId(),
                'userInstance' => $user->getId(),
                'userGroup' => $user->getGroup()
            ]
        );
    }

    /**
     * Log la suppression d'une entité.
     * @param AbstractLifecycleEvent $event
     * @return void
     */
    public function logDeleting(AbstractLifecycleEvent $event): void
    {
        $entity = $event->getEntityInstance();
        if (!($entity instanceof User || $entity instanceof Notice)) {
            return;
        }

        /** @var User $user */
        $user = $this->security->getUser();
        $this->untLogger->alert(
            sprintf("%s vient d'être <<supprimé>> par %s", $entity, $user),
            [
                'actionType' => 'suppression',
                'ressType' => Notice::class,
                'ressInstance' => $entity->getId(),
                'userInstance' => $user->getId(),
                'userGroup' => $user->getGroup()
            ]
        );
    }

    /**
     * Log la création d'une entité.
     * @param AbstractLifecycleEvent $event
     * @return void
     */
    public function logCreating(AbstractLifecycleEvent $event): void
    {
        $entity = $event->getEntityInstance();
        if (!($entity instanceof User || $entity instanceof Notice)) {
            return;
        }

        /** @var User $user */
        $user = $this->security->getUser();
        $this->untLogger->info(
            sprintf("%s vient d'être <<créée>> par %s", $entity, $user),
            [
                'actionType' => 'creation',
                'ressType' => Notice::class,
                'ressInstance' => $entity->getId(),
                'userInstance' => $user->getId(),
                'userGroup' => $user->getGroup()
            ]
        );
    }

    /**
     * Log la modification d'une entité.
     * @param AbstractLifecycleEvent $event
     * @return void
     */
    public function logUpdating(AbstractLifecycleEvent $event): void
    {
        $entity = $event->getEntityInstance();
        if (!($entity instanceof User || $entity instanceof Notice)) {
            return;
        }

        /** @var User $user */
        $user = $this->security->getUser();
        $this->untLogger->warning(
            sprintf("%s vient d'être <<modifiée>> par %s", $entity, $user),
            [
                'actionType' => 'modification',
                'ressType' => Notice::class,
                'ressInstance' => $entity->getId(),
                'userInstance' => $user->getId(),
                'userGroup' => $user->getGroup()
            ]
        );
    }

    /**
     * Met à jour la date d'édition et le validateur si besoin.
     * @param BeforeEntityUpdatedEvent $event
     * @return void
     */
    public function onDateSetting(BeforeEntityUpdatedEvent $event): void
    {
        $entity = $event->getEntityInstance();
        if ($entity instanceof Notice) {
            /** @var User $user */
            $user = $this->security->getUser();
            if ($this->security->isGranted('ROLE_VALI_NOTI')) {
                $entity->setValidateur($user);
            }
            $entity->setEditeLe(new DateTime());
        }
    }

    /**
     * Log la connexion d'un utilisateur.
     * @param LoginSuccessEvent $event
     * @return void
     */
    #[AsEventListener(event: LoginSuccessEvent::class)]
    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        $clientIp = $event->getRequest()->getClientIp();
        /** @var User $user */
        $user = $event->getUser();

        $this->untLogger->debug(
            sprintf("%s vient de se connecter au système", $user),
            [
                'userId' => $user->getId(),
                'remoteIp' => $clientIp,
            ]
        );
    }
}