<?php

namespace App\Entity;

use App\Repository\KeywordRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\SerializedName;

#[ORM\Entity(repositoryClass: KeywordRepository::class)]
class Keyword
{
    #[ORM\Id,ORM\Column]
    #[ORM\GeneratedValue]
    private ?int $id = null;

    #[ORM\Column(length: 255), SerializedName('text')]
    private ?string $nom;

    #[ORM\Column(nullable: true)]
    private ?bool $valide;

    #[SerializedName('value')]
    public function getValue(): ?string
    {
        return $this->nom;
    }

    public function __construct(string $name = null,bool $valid = false)
    {
        $this->nom = $name;
        $this->valide = $valid;
    }

    public function getId(): ?int
    {
        return $this->id;
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
