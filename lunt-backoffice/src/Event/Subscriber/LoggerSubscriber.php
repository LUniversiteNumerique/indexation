<?php

namespace App\Event\Subscriber;

use EasyCorp\Bundle\EasyAdminBundle\Event\{AbstractLifecycleEvent, AfterEntityDeletedEvent, AfterEntityPersistedEvent, AfterEntityUpdatedEvent, BeforeEntityUpdatedEvent};
use App\Event\AfterNoticeStateSetEvent;
use App\Entity\{IndexingConfig, Notice, User};
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\{EventSubscriberInterface,Attribute\AsEventListener};
use Symfony\Component\Console\Messenger\RunCommandMessage;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

readonly class LoggerSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private MessageBusInterface $messageBus,
        private LoggerInterface     $untLogger,
        private Security            $security,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            AfterNoticeStateSetEvent::class => 'logChanging',
            AfterEntityDeletedEvent::class => [['logDeleting', 0], ['schTrigging', 10],],
            AfterEntityPersistedEvent::class => [['logCreating', 0], ['schTrigging', 10],],
            AfterEntityUpdatedEvent::class => [['logUpdating', 0], ['schTrigging', 10],],
            BeforeEntityUpdatedEvent::class => ['onDateSetting'],
            LoginSuccessEvent::class => 'onLoginSuccess',
        ];
    }

    public function logChanging(AfterNoticeStateSetEvent $event): void
    {
        $entity = $event->getEntityInstance();
        if (!$entity instanceof Notice) return;
        $action = $event->getAction();

        /** @var User $user */
        $user = $this->security->getUser();
        $this->untLogger->notice(sprintf("%s vient d'être <<%s>> par %s", $entity, $action[1], $user),
            ['actionType'=> $action[2], 'ressType'=>Notice::class, 'ressInstance'=>$entity->getId(), 'userInstance'=>$user->getId(),'userGroup'=>$user->getGroup()]);
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

    public function schTrigging(AfterEntityDeletedEvent|AfterEntityPersistedEvent|AfterEntityUpdatedEvent $event): void
    {
        $entity = $event->getEntityInstance();
        if (!($entity instanceof IndexingConfig)) return;

        //$this->messageBus->dispatch(new RunCommandMessage('app:exec-scheduler'));
    }
}