<?php

namespace App\Entity;

use App\Repository\AuteurRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: AuteurRepository::class)]
class Auteur
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255), Assert\NotBlank]
    private ?string $nom = null;

    #[ORM\Column(length: 255), Assert\NotBlank]
    private ?string $prenom = null;

    #[ORM\Column(length: 255), Assert\Email]
    private ?string $email = null;

    #[ORM\Column(type: Types::SIMPLE_ARRAY, nullable: true)]
    private ?array $roles = null;

    #[ORM\ManyToOne]
    private ?Etablissement $ecole = null;

    public function getId(): ?int
    {
        return $this->id;
    }
    public function getNomComplet(): string
    {
        return $this->getPrenom().' '.$this->getNom();
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

    public function getPrenom(): ?string
    {
        return $this->prenom;
    }

    public function setPrenom(string $prenom): static
    {
        $this->prenom = $prenom;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getRoles(): ?array
    {
        return $this->roles;
    }

    public function setRoles(?array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    public function getEcole(): ?Etablissement
    {
        return $this->ecole;
    }

    public function setEcole(?Etablissement $ecole): static
    {
        $this->ecole = $ecole;

        return $this;
    }

    public function __toString(): string
    {
        return sprintf('%s %s', $this->prenom, $this->nom);
    }
}
