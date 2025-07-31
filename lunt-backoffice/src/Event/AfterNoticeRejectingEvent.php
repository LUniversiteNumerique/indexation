<?php

namespace App\Event;

use EasyCorp\Bundle\EasyAdminBundle\Event\AbstractLifecycleEvent;

class AfterNoticeRejectingEvent  extends AbstractLifecycleEvent
{
    public function __construct(protected $entityInstance, private readonly string $motifs)
    {
        parent::__construct($this->entityInstance);
    }

    public function getMotifs(): string
    {
        return $this->motifs;
    }
}