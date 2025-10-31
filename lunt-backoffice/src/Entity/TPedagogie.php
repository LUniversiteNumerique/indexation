<?php

namespace App\Entity;

use App\Repository\TPedagogieRepository;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: TPedagogieRepository::class)]
class TPedagogie
{
    use Timestamps;

    #[ORM\Column(length: 225, unique: true), Assert\NotBlank]
    private ?string $code;

    #[ORM\Column(length: 255), Assert\NotBlank]
    private ?string $nom, $suplom;

    public function __construct($code = null, $nom = null, $suplom = null)
    {
        $this->creeLe = new DateTimeImmutable();
        $this->code = $code;
        $this->nom = $nom;
        $this->suplom = $suplom;
    }

    public static function create(array $o): self
    {
        return new self($o['id'], $o['libelle_uoh'], $o['libelle_suplomfr']);
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(string $code): static
    {
        $this->code = $code;

        return $this;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(string $nom): static
    {
        $this->nom = $nom;

        return $this;
    }

    public function getSuplom(): ?string
    {
        return $this->suplom;
    }

    public function setSuplom(string $suplom): static
    {
        $this->suplom = $suplom;

        return $this;
    }

    public function __toString(): string
    {
        return $this->nom;
    }
}
