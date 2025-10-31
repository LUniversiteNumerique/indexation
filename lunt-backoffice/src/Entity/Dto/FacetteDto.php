<?php

namespace App\Entity\Dto;

use JMS\Serializer\Annotation as Jms;
use Symfony\Component\Uid\Uuid;

/**
 * DTO représentant une facette composée de champs.
 */
#[Jms\XmlRoot("facettes")]
class FacetteDto
{
    /**
     * @var FieldDto[] Liste des champs de la facette
     */
    #[Jms\XmlList(entry: "field", inline: true), Jms\Type("array<" . FieldDto::class . ">")]
    public array $data = [];

    /**
     * @param FieldDto[] $data
     */
    public function __construct(array $data = [])
    {
        $this->data = $data;
    }

    /**
     * Crée une instance de FacetteDto avec des champs par défaut.
     *
     * @return FacetteDto
     */
    public static function create(): FacetteDto
    {
        return new self([
            new FieldDto('uuid', Uuid::v4()),
            new FieldDto('titre', "Facette 1"),
            new FieldDto('vignette', "Facette 2"),
        ]);
    }
}

/**
 * DTO représentant un champ d'une facette.
 */
class FieldDto
{
    /**
     * @param string $name  Nom du champ
     * @param string|null $value  Valeur du champ
     */
    public function __construct(
        #[Jms\XmlAttribute] public string $name,
        #[Jms\XmlValue(cdata: false)] public ?string $value
    ) {}
}