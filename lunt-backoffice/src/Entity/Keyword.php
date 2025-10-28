<?php

namespace App\Entity;

use App\Repository\KeywordRepository;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: KeywordRepository::class),
    UniqueEntity('nom', 'ce nom est déjà utilisé.')]
class Keyword
{
    use Timestamps;

    #[ORM\Column(length: 255, unique: true), Assert\NotBlank]
    private ?string $nom;

    #[ORM\Column, Assert\Type('bool')]
    private bool $valide;

    public function __construct(?string $name = null, bool $valid = false)
    {
        $this->nom = $name;
        $this->valide = $valid;
        $this->creeLe = new DateTimeImmutable();
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(?string $nom): static
    {
        $this->nom = $nom;

        return $this;
    }

    public function isValide(): ?bool
    {
        return $this->valide;
    }

    public function setValide(?bool $valide): static
    {
        $this->valide = $valide;

        return $this;
    }

    public function __toString(): string
    {
        return $this->nom;
    }
}
