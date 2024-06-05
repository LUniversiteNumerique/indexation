<?php

namespace App\Message;

final readonly class ExtexingConfigMessage
{
    public function __construct(public int $taskId){}
}