<?php

namespace App\Entity;

use Symfony\Component\Validator\Constraints as Assert;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Embeddable]
class Ressource
{
    #[ORM\Column(length: 255),Assert\NotBlank]
    private ?string $libelle = null;

    #[ORM\Column(length: 255),Assert\Url]
    private ?string $url = null;

    public function __construct() {}

    public function getLibelle(): ?string
    {
        return $this->libelle;
    }

    public function setLibelle(string $libelle): static
    {
        $this->libelle = $libelle;

        return $this;
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function setUrl(string $url): static
    {
        $this->url = $url;

        return $this;
    }

    public function __toString(): string
    {
        return sprintf('%s(%s)', $this->url, $this->libelle);
    }
}
