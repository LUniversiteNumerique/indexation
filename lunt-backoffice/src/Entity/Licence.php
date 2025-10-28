<?php

namespace App\Entity;

use App\Repository\LicenceRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: LicenceRepository::class)]
class Licence
{
    use Timestamps;

    #[ORM\Column(length: 255), Assert\NotBlank]
    private ?string $code;

    #[ORM\Column(type: Types::TEXT), Assert\NotBlank]
    private ?string $valeur;

    public function __construct($code = null, $valeur = null)
    {
        $this->creeLe = new DateTimeImmutable();
        $this->code = $code;
        $this->valeur = $valeur;
    }

    public static function create(array $o): self
    {
        return new self($o['id'], $o['libelle_uoh']);
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

    public function getValeur(): ?string
    {
        return $this->valeur;
    }

    public function setValeur(string $valeur): static
    {
        $this->valeur = $valeur;

        return $this;
    }

    public function __toString(): string
    {
        return $this->valeur;
    }
}
