<?php

namespace App\Entity\Dto;

use JMS\Serializer\Annotation\{SerializedName, Type, XmlAttribute, XmlList, XmlElement, XmlRoot};

/**
 * Classe représentant un concept Dewey.
 */
class DeweyDto
{
    /**
     * URI du concept.
     *
     * @var string|null
     */
    #[XmlAttribute, Type('string')]
    public ?string $uri;

    /**
     * Niveau du concept.
     *
     * @var int|null
     */
    #[XmlAttribute, Type('int')]
    public ?int $level;

    /**
     * Notation Dewey.
     *
     * @var string|null
     */
    #[XmlElement(cdata: false, namespace: "http://www.uoh.fr/dewey")]
    #[SerializedName('Notation')]
    #[Type('string')]
    public ?string $notation;

    /**
     * Libellé Dewey.
     *
     * @var string|null
     */
    #[XmlElement(cdata: false, namespace: "http://www.uoh.fr/dewey")]
    #[SerializedName('Label')]
    #[Type('string')]
    public ?string $label;

    /**
     * @param string|null $uri
     * @param int|null $level
     * @param string|null $notation
     * @param string|null $label
     */
    public function __construct(
        ?string $uri = null,
        ?int $level = null,
        ?string $notation = null,
        ?string $label = null
    ) {
        $this->uri = $uri;
        $this->level = $level;
        $this->notation = $notation;
        $this->label = $label;
    }
}

/**
 * Classe contenant une liste de concepts Dewey.
 */
#[XmlRoot("DeweyCodes", namespace: "http://www.uoh.fr/dewey", prefix: 'ns1')]
class DeweyData
{
    /**
     * Liste des concepts Dewey.
     *
     * @var DeweyDto[]
     */
    #[XmlList(entry: "Concept", inline: true, namespace: "http://www.uoh.fr/dewey")]
    #[Type("array<" . DeweyDto::class . ">")]
    public array $concepts;

    /**
     * @param DeweyDto[] $concepts
     */
    public function __construct(array $concepts = [])
    {
        $this->concepts = $concepts;
    }
}