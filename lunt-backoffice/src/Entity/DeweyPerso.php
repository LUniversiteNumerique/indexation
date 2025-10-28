<?php

namespace App\Entity;

use App\Repository\DeweyPersoRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DeweyPersoRepository::class)]
class DeweyPerso
{
  #[ORM\Id]
  #[ORM\GeneratedValue]
  #[ORM\Column(type: 'integer')]
  private ?int $id = null;

  #[ORM\Column(length: 255, unique: true)]
  private ?string $code = null;

  #[ORM\Column(length: 255)]
  private ?string $nom = null;

  public function getId(): ?int
  {
    return $this->id;
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
    $numero = '';
    $parts = explode('/', trim($this->code, '/'));

    $classIndex = array_search('class', $parts);
    // recupère l'élément après class dans l'url
    if ($classIndex !== false && isset($parts[$classIndex + 1])) {
      $numero = $parts[$classIndex + 1];
    }

    return $numero . ' - ' . $this->nom;
  }
}
