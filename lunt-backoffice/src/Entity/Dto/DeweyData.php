<?php

namespace App\Entity\Dto;

use JMS\Serializer\Annotation\{SerializedName, Type, XmlAttribute, XmlList, XmlElement, XmlRoot};

class DeweyDto
{
    public function __construct(
        #[XmlAttribute,Type('string')] public ?string $uri = null,
        #[XmlAttribute,Type('int')] public ?int $level = null,

        #[XmlElement(cdata:false, namespace:"http://www.uoh.fr/dewey"),
            SerializedName('Notation'),Type('string')]
        public ?string $notation = null,
        #[XmlElement(cdata:false, namespace:"http://www.uoh.fr/dewey"),
            SerializedName('Label'),Type('string')]
        public ?string $label = null,
    ) {}
}

#[XmlRoot("DeweyCodes", namespace:"http://www.uoh.fr/dewey", prefix: 'ns1')]
class DeweyData
{
    public function __construct(
        /** @var DeweyDto[] */
        #[XmlList(entry: "Concept", inline: true, namespace: "http://www.uoh.fr/dewey"),
            Type("array<".DeweyDto::class.">")]
        public array $concepts = [],
    ) {}
}