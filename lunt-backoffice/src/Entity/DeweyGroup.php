<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
class DeweyGroup
{
  #[ORM\Id, ORM\GeneratedValue, ORM\Column(type: 'integer')]
  private $id;

  #[ORM\ManyToOne(targetEntity: Dewey::class)]
  private ?Dewey $dewey;

  #[ORM\Column(type: 'string', nullable: true)]
  private ?string $code;

  #[ORM\Column(type: 'string', nullable: true)]
  private ?string $libelle;

  #[ORM\ManyToOne(targetEntity: Notice::class, cascade: ['persist'], inversedBy: 'deweyGroups')]
  private ?Notice $notice;

  #[ORM\ManyToOne(targetEntity: Dewey::class)]
  private ?Dewey $division;

  #[ORM\ManyToMany(targetEntity: Dewey::class)]
  #[Assert\Count(min: 1, minMessage: 'Au moins un code Dewey doit être sélectionnée.')]
  private $codeweys;

  public function __construct()
  {
    $this->codeweys = new ArrayCollection();
  }

  public function __toString(): string
  {
    return '';
  }

  public function getId(): ?int
  {
    return $this->id;
  }

  public function getDewey(): ?Dewey
  {
    return $this->dewey;
  }

  public function setDewey(?Dewey $dewey): self
  {
    $this->dewey = $dewey;
    return $this;
  }

  public function getCode(): ?string
  {
    return $this->code;
  }

  public function setCode(?string $code): self
  {
    $this->code = $code;
    return $this;
  }

  public function getLibelle(): ?string
  {
    return $this->libelle;
  }

  public function setLibelle(?string $libelle): self
  {
    $this->libelle = $libelle;
    return $this;
  }

  public function getNotice(): ?Notice
  {
    return $this->notice;
  }

  public function setNotice(?Notice $notice): self
  {
    $this->notice = $notice;
    return $this;
  }

  public function getDivision(): ?Dewey
  {
    return $this->division;
  }

  public function setDivision(?Dewey $division): self
  {
    $this->division = $division;
    return $this;
  }

  public function getCodeweys()
  {
    return $this->codeweys;
  }

  public function addCodewey(Dewey $codewey): self
  {
    if (!$this->codeweys->contains($codewey)) {
      $this->codeweys[] = $codewey;
    }
    return $this;
  }

  public function removeCodewey(Dewey $codewey): self
  {
    $this->codeweys->removeElement($codewey);
    return $this;
  }

}
