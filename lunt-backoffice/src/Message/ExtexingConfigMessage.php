<?php

namespace App\Message;

final readonly class ExtexingConfigMessage
{
    public function __construct(public ?string $indexKey, public int $taskId){}
}