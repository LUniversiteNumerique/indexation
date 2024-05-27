<?php

namespace App\Message;

final readonly class IntexingConfigMessage
{
    public function __construct(public bool $isFullExec = false, public array $itemIds = []){}
}