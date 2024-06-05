<?php

namespace App\Message;

final readonly class IntexingConfigMessage
{
    public function __construct(public int $taskId){}
}