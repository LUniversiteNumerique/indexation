<?php

namespace App\Event;

use App\Entity\User;
use Symfony\Contracts\EventDispatcher\Event;

class UserPassSettingEvent  extends Event
{
    public function __construct(public User $user, public bool $type = false){}
}