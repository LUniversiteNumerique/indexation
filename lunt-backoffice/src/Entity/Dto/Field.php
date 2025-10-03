<?php

namespace App\Entity\Dto;

use JMS\Serializer\Annotation as Jms;

class Field
{
    public function __construct(
        #[Jms\XmlValue(cdata: false), Jms\Type('string')] public ?string $value,
        #[Jms\XmlAttribute, Jms\Type('string'), Jms\SerializedName('language')] public ?string $lang,
        #[Jms\XmlAttribute, Jms\Type('string'), Jms\SerializedName('name')] public ?string $name
    ){}
}