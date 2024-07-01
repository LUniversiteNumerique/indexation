<?php

namespace App\Entity;

use App\Repository\KeywordRepository;
use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation\{SerializedName, VirtualProperty};
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: KeywordRepository::class)]
class Keyword
{
    use Timestamps;

    #[ORM\Column(length: 255),
        Assert\NotBlank, SerializedName('text')]
    private ?string $nom;

    #[ORM\Column(nullable: true), Assert\Type('bool')]
    private ?bool $valide;

    public function __construct(string $name = null,bool $valid = false)
    {
        $this->nom = $name;
        $this->valide = $valid;
        $this->creeLe = new \DateTimeImmutable();
    }

    #[VirtualProperty]
    public function getValue(): ?string
    {
        return $this->nom;
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
