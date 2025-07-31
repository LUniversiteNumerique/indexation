<?php

namespace App\Entity;

use Symfony\Component\Validator\Constraints as Assert;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Embeddable]
class Frequence
{
    #[ORM\Column, Assert\Range(min: 0)]
    private ?int $valeur;

    #[ORM\Column(length: 10, enumType: NoticEtat::class)]
    private ?DateUnit $unite;

    public function __construct(?int $valeur = null, ?DateUnit $unite = null)
    {
        $this->valeur = $valeur;
        $this->unite = $unite;
    }

    public function getValeur(): ?int
    {
        return $this->valeur;
    }

    public function setValeur(?int $valeur): self
    {
        $this->valeur = $valeur;

        return $this;
    }

    public function getUnite(): ?DateUnit
    {
        return $this->unite;
    }

    public function setUnite(?DateUnit $unite): self
    {
        $this->unite = $unite;

        return $this;
    }

    public function __toString(): string
    {
        return sprintf('%d %s', $this->valeur, $this->unite?->value);
    }
}