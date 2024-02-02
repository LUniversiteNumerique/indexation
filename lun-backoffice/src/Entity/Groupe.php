<?php

namespace App\Entity;

use App\Repository\GroupeRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: GroupeRepository::class)]
class Groupe
{
    const PERMISSIONS = array(
        "ROLE_CONTR" => 'Contributeur',
        "ROLE_DOCUM" => 'Documentaliste',
        "ROLE_ADMIN" => 'Administrateur'
    );

    #[ORM\Id,ORM\Column]
    #[ORM\GeneratedValue]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $label = null;

    #[ORM\Column]
    private array $rights = [];

    public function __construct() {}

    public function hasRight($name): bool
    {
        return in_array($name, $this->getRights(),true);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(string $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function getRights(): array
    {
        return $this->rights;
    }

    public function setRights(array $rights): static
    {
        $this->rights = $rights;

        return $this;
    }

    public function __toString(): string
    {
        return $this->label;
    }
}
