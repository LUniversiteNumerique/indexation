<?php

namespace App\Entity\Dto;


use Symfony\Component\Serializer\Attribute\{SerializedName,SerializedPath};

class DeweyDto
{
    public function __construct(
        /** @var ConceptDto $concept */
        #[SerializedName('Concept')]
        #[SerializedPath('[Concept]')]
        public array $concept = [],
    ) {}
}

class ConceptDto
{
    public function __construct(
        #[SerializedName('@uri')]
        public ?string $uri = null,
        #[SerializedName('@level')]
        public ?int $level = null,
        #[SerializedName('Notation')]
        public ?string $notation = null,
        #[SerializedName('Label')]
        public ?string $label = null,
    ) {}
}