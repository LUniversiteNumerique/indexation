<?php

namespace App\Entity\Dto;

use JMS\Serializer\Annotation as Jms;

/**
 * DTO représentant un champ sérialisable.
 */
class Field
{
    /**
     * @param string|null $value Valeur du champ.
     * @param string|null $lang  Langue du champ.
     * @param string|null $name  Nom du champ.
     */
    public function __construct(
        #[Jms\XmlValue(cdata: false), Jms\Type('string')]
        public ?string $value,
        #[Jms\XmlAttribute, Jms\Type('string'), Jms\SerializedName('language')]
        public ?string $lang,
        #[Jms\XmlAttribute, Jms\Type('string'), Jms\SerializedName('name')]
        public ?string $name
    ) {}
}