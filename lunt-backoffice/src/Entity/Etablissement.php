<?php

namespace App\Entity;

use App\Repository\EtablissementRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: EtablissementRepository::class),
    UniqueEntity('abrege')]
class Etablissement
{
    use Timestamps;

    #[ORM\Column(length: 255), Assert\NotBlank]
    private ?string $nom;

    #[ORM\Column(length: 225, unique: true), Assert\NotBlank]
    private ?string $abrege;

    public function __construct($abrege=null, $nom=null)
    {
        $this->creeLe = new \DateTimeImmutable();
        $this->abrege = $abrege;
        $this->nom = $nom;
    }
    public static function create(array $o): self
    {
        return new self($o['id'],$o['libelle_uoh']);
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

    public function getAbrege(): ?string
    {
        return $this->abrege;
    }

    public function setAbrege(?string $abrege): static
    {
        $this->abrege = $abrege;

        return $this;
    }

    public function __toString(): string
    {
        return $this->nom;
    }
}
