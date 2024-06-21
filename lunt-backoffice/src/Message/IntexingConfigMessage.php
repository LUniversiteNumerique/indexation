<?php

namespace App\Message;

final readonly class IntexingConfigMessage
{
    public function __construct(public string $indexKey, public int $taskId){}
}