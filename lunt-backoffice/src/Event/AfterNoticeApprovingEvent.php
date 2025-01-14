<?php

namespace App\Event;

use EasyCorp\Bundle\EasyAdminBundle\Event\AbstractLifecycleEvent;

class AfterNoticeApprovingEvent  extends AbstractLifecycleEvent
{
    public function __construct(protected $entityInstance)
    {
        parent::__construct($this->entityInstance);
    }
}