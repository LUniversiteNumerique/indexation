<?php

namespace App\Entity\Dto;

use JMS\Serializer\Annotation\{Type, XmlAttribute, XmlList, XmlRoot, XmlValue};

class PropertyDto
{
    public function __construct(
        #[XmlAttribute, Type('string')]
        public string $key,
        #[XmlValue(cdata:false), Type('string')]
        public string $value,
    ) {}
}

class SpecialiteDto
{
    public function __construct(
        #[XmlList(entry: "property", inline: true),
            Type("array<".PropertyDto::class.">")]
        public array $properties = [],

        #[XmlList(entry: "item", inline: true),
            Type("array<".SpecialiteDto::class.">")]
        public array $children = [],
    ){}
}

#[XmlRoot("tables")]
class DisciplineData
{
    public function __construct(
        #[XmlList(entry: "item", inline: true),
            Type("array<".SpecialiteDto::class.">")]
        public array $items = []
    ){}
}