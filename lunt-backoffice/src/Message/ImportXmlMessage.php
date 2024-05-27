<?php

namespace App\Message;

final readonly class ImportXmlMessage
{
    public function __construct(
        public string $name,
        public string $path
    ){}
}