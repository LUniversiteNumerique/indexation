<?php

namespace App\Event\Subscriber;

use EasyCorp\Bundle\EasyAdminBundle\Event\{AbstractLifecycleEvent, AfterEntityDeletedEvent, AfterEntityPersistedEvent, AfterEntityUpdatedEvent, BeforeEntityUpdatedEvent};
use App\Controller\NoticeCrudController;
use App\Event\{AfterNoticeAdjustingEvent,AfterNoticeApprovingEvent,AfterNoticeRejectingEvent,AfterNoticeStateSetEvent};
use App\Service\MailerService;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use App\Entity\{Notice, User};
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\{EventSubscriberInterface,Attribute\AsEventListener};
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

readonly class LoggerSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private Security        $security,
        private MailerService   $mailer,
        private LoggerInterface $untLogger,
        private AdminUrlGenerator $generator
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
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

    public function noticeAdjusting(AfterNoticeAdjustingEvent $event): void
    {
        /** @var Notice $entity */ $entity = $event->getEntityInstance();
        /** @var User $user */ $user = $this->security->getUser();

        $to = $entity->getValidateur();
        $this->untLogger->notice(sprintf("%s demande de rectifier la notice %s", $user, $entity), [
            'actionType'=> 'Rectification',
            'ressType' => Notice::class,
            'ressInstance' => $entity->getId(),
            'userInstance' => $user->getId(),
            'userGroup' => $user->getGroup()
        ]);

        $url = $this->generator
            ->setController(NoticeCrudController::class)
            ->setAction(Action::DETAIL)->setEntityId($entity->getId())
            ->generateUrl();

        $this->mailer->sendTwig($to?->getEmail(), //$to?->getUntheme()?->getEmail()
            sprintf("[UNT] Demande de Rectification de la notice %d", $entity->getId()),
            'emails/ajust.html.twig', ['user' => $user, 'url' => $url, 'notice' => $entity->getTitre(),]
        );
    }

    public function noticeApproving(AfterNoticeApprovingEvent $event): void
    {
        /** @var Notice $entity */ $entity = $event->getEntityInstance();
        /** @var User $user */ $user = $this->security->getUser();

        $from = $entity->getCreateur();
        $this->untLogger->notice(sprintf("%s vient d'être validée par %s", $entity, $user), [
            'actionType'=> 'Validation',
            'ressType' => Notice::class,
            'ressInstance' => $entity->getId(),
            'userInstance' => $user->getId(),
            'userGroup' => $user->getGroup()
        ]);

        $url = $this->generator
            ->setController(NoticeCrudController::class)
            ->setAction(Action::DETAIL)->setEntityId($entity->getId())
            ->generateUrl();

        $this->mailer->sendTwig($from->getEmail(), //$from->getSchool()?->getEmail()
            sprintf("[UNT] La notice N° %d est validée", $entity->getId()),
            'emails/approve.html.twig', ['user' => $user, 'url' => $url, 'notice' => $entity->getTitre()]
        );
    }

    public function noticeRejecting(AfterNoticeRejectingEvent $event): void
    {
        /** @var Notice $entity */ $entity = $event->getEntityInstance();
        /** @var User $user */ $user = $this->security->getUser();

        $motif = $event->getMotifs();
        $from = $entity->getCreateur();
        $this->untLogger->notice(sprintf("%s vient d'être rejetée par %s pour <<%s>>", $entity, $user, $motif), [
            'actionType'=> 'Rejet',
            'ressType' => Notice::class,
            'ressInstance' => $entity->getId(),
            'userInstance' => $user->getId(),
            'userGroup' => $user->getGroup()
        ]);

        $url = $this->generator
            ->setController(NoticeCrudController::class)
            ->setAction(Action::DETAIL)->setEntityId($entity->getId())
            ->generateUrl();

        $this->mailer->sendTwig($from->getEmail(), //$from->getSchool()?->getEmail()
            sprintf("[UNT] La notice N° %d est rejetée", $entity->getId()),
            'emails/reject.html.twig', ['user' => $user, 'url' => $url, 'motifs' => $motif, 'notice' => $entity->getTitre(),]
        );
    }

    public function logChanging(AfterNoticeStateSetEvent $event): void
    {
        /** @var User $user */
        $user = $this->security->getUser();
        $action = $event->getAction();
        $entity = $event->getEntityInstance();
        if (!$entity instanceof Notice) return;

        $this->untLogger->notice(sprintf("%s vient d'être <<%s>> par %s", $entity, $action[1], $user), [
            'actionType'=> $action[2],
            'ressType' => Notice::class,
            'ressInstance' => $entity->getId(),
            'userInstance' => $user->getId(),
            'userGroup' => $user->getGroup()
        ]);
    }

    public function logDeleting(AbstractLifecycleEvent $event): void
    {
        $entity = $event->getEntityInstance();
        if (!($entity instanceof User || $entity instanceof Notice)) return;

        /** @var User $user */
        $user = $this->security->getUser();
        $this->untLogger->alert(sprintf("%s vient d'être <<supprimé>> par %s", $entity, $user
        ),['actionType'=>'suppression', 'ressType'=>Notice::class, 'ressInstance'=>$entity->getId(), 'userInstance'=>$user->getId(),'userGroup'=>$user->getGroup()]);
    }

    public function logCreating(AbstractLifecycleEvent $event): void
    {
        $entity = $event->getEntityInstance();
        if (!($entity instanceof User || $entity instanceof Notice)) return;

        /** @var User $user */
        $user = $this->security->getUser();
        $this->untLogger->info(sprintf("%s vient d'être <<créée>> par %s", $entity, $user
        ),['actionType'=>'creation', 'ressType'=>Notice::class, 'ressInstance'=>$entity->getId(), 'userInstance'=>$user->getId(),'userGroup'=>$user->getGroup()]);
    }

    public function logUpdating(AbstractLifecycleEvent $event): void
    {
        $entity = $event->getEntityInstance();
        if (!($entity instanceof User || $entity instanceof Notice)) return;

        /** @var User $user */
        $user = $this->security->getUser();
        $this->untLogger->warning(sprintf("%s vient d'être <<modifiée>> par %s", $entity, $user),
            ['actionType'=>'modification', 'ressType'=>Notice::class, 'ressInstance'=>$entity->getId(), 'userInstance'=>$user->getId(),'userGroup'=>$user->getGroup()]);
    }
    public function onDateSetting(BeforeEntityUpdatedEvent $event): void
    {
        $entity = $event->getEntityInstance();
        if ($entity instanceof Notice) {
            /** @var User $user */
            $user = $this->security->getUser();
            if($this->security->isGranted('ROLE_VALI_NOTI'))
                $entity->setValidateur($user);
            $entity->setEditeLe(new \DateTime());
        }
    }

    #[AsEventListener(event: LoginSuccessEvent::class)]
    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        $clientIp = $event->getRequest()->getClientIp();
        /** @var User $user */
        $user = $event->getUser();

        //$this->repository->add($user->setLastLogin(new \DateTime()));
        $this->untLogger->debug(sprintf("%s vient de se connecter au système",$user), [
            'userId' => $user->getId(), 'remoteIp' => $clientIp,
        ]);
    }
}