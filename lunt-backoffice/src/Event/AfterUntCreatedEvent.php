<?php

namespace App\Event;

use App\Entity\Univerique;

class AfterUntCreatedEvent
{
    public function __construct(private Univerique $unt) {}

    public function getUnt(): Univerique {
        return $this->unt;
    }
}