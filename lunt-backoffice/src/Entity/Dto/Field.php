<?php

namespace App\Entity\Dto;

use JMS\Serializer\Annotation as Jms;

class Field
{
    public function __construct(
        #[Jms\XmlAttribute, Jms\SerializedName('name')] public string $key,
        #[Jms\XmlValue(cdata: false), Jms\Type('string')] public null|string|bool|array   $value
    ){}
}