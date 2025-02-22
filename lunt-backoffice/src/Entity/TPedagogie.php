<?php

namespace App\Entity;

use App\Repository\TPedagogieRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: TPedagogieRepository::class)]
class TPedagogie
{
    use Timestamps;

    #[ORM\Column(length: 255), Assert\NotBlank]
    private ?string $code,$nom;

    public function __construct($code=null,$nom=null)
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

    public function __toString(): string
    {
        return $this->nom;
    }
}
