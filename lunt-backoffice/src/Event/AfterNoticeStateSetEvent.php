<?php

namespace App\Event;

use EasyCorp\Bundle\EasyAdminBundle\Event\AbstractLifecycleEvent;

class AfterNoticeStateSetEvent  extends AbstractLifecycleEvent
{
    public function __construct(protected $entityInstance, private readonly array $action)
    {
        parent::__construct($this->entityInstance);
    }

    public function getAction(): array
    {
        return $this->action;
    }
}