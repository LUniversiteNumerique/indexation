<?php

namespace App\Message;

use Symfony\Component\Lock\Key;

final readonly class IntexingConfigMessage
{
    public function __construct(public Key $indexKey, public int $taskId){}
}