<?php

namespace App\Entity;

use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;
use App\Repository\AuteurRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AuteurRepository::class),
    UniqueEntity('email', 'Un auteur avec cet email existe déjà.')]
class Auteur
{
    use Timestamps;

    #[ORM\Column(length: 255), Assert\NotBlank]
    private ?string $nom = null;

    #[ORM\Column(length: 255),
        Assert\NotBlank, Assert\Length(max: 225)]
    private ?string $prenom = null;

    #[ORM\Column(length: 255, unique: true),
        Assert\NotNull, Assert\Email]
    private ?string $email = null;

    #[ORM\ManyToOne] private ?Etablissement $etablissement = null;

    public function __construct()
    {
        $this->creeLe = new \DateTimeImmutable();
    }

    public function getName(): string
    {
        return $this->getPrenom().' '.$this->getNom();
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

    public function getPrenom(): ?string
    {
        return $this->prenom;
    }

    public function setPrenom(?string $prenom): static
    {
        $this->prenom = $prenom;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getEtablissement(): ?Etablissement
    {
        return $this->etablissement;
    }

    public function setEtablissement(?Etablissement $etablissement): static
    {
        $this->etablissement = $etablissement;

        return $this;
    }

    public function __toString(): string
    {
        return sprintf('%s %s', $this->prenom, $this->nom);
    }
}
