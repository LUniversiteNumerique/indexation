<?php

namespace App\Entity;

use App\Repository\EtablissementRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: EtablissementRepository::class),
    UniqueEntity('code')]
class Etablissement
{
    use Timestamps;

    #[ORM\Column(length: 225, unique: true), Assert\NotBlank]
    private ?string $code;

    #[ORM\Column(length: 255), Assert\NotBlank]
    private ?string $nom;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $logo;

    public function __construct(?string $code=null, ?string $nom=null)
    {
        $this->creeLe = new \DateTimeImmutable();
        $this->code = $code;
        $this->nom = $nom;
    }
    public static function create(array $o): self
    {
        return new self($o['id'],$o['libelle_import']);
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(?string $code): static
    {
        $this->code = $code;

        return $this;
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

    public function getLogo(): ?string
    {
        return $this->logo;
    }

    public function setLogo(?string $logo): static
    {
        $this->logo = $logo;

        return $this;
    }

    public function __toString(): string
    {
        return $this->nom;
    }
}
