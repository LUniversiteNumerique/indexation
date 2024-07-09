<?php

namespace App\Entity\Dto;

use JMS\Serializer\Annotation as Jms;
use Symfony\Component\Uid\Uuid;

#[Jms\XmlRoot("facettes")]
class FacetteDto
{
    public function __construct(#[
        Jms\XmlList(entry: "field", inline: true), 
        Jms\Type("array<".FieldDto::class.">"
    )] public array $data = []){}

    static function create(): FacetteDto
    {
        return new self([
            new FieldDto('uuid', Uuid::v4()),
            new FieldDto('titre', "Facette 1"),
            new FieldDto('vignette', "Facette 2"),
        ]);
    }
}

class FieldDto
{
    public function __construct(
        #[Jms\XmlAttribute] public string $name,
        #[Jms\XmlValue(cdata: false)] public ?string   $value
    ){}
}