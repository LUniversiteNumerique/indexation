<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
class DisciplineGroup
{
  #[ORM\Id]
  #[ORM\GeneratedValue]
  #[ORM\Column(type: 'integer')]
  private ?int $id = null;

  #[ORM\ManyToOne(targetEntity: Discipline::class)]
  private ?Discipline $champDisc = null;

  #[ORM\ManyToOne(targetEntity: Discipline::class)]
  private ?Discipline $discipline = null;

  #[ORM\ManyToMany(targetEntity: Discipline::class)]
  #[ORM\JoinTable(name: 'discipline_group_specialites')]
  #[Assert\Count(min: 1, minMessage: 'Au moins une sous-discipline doit être sélectionnée.')]
  private Collection $specialites;

  #[ORM\ManyToOne(targetEntity: Notice::class, inversedBy: 'disciplineGroups', cascade: ['persist'])]
  #[ORM\JoinColumn(nullable: false)]
  private ?Notice $notice = null;

  public function __construct()
  {
    $this->specialites = new ArrayCollection();
  }

  public function __toString(): string
  {
      return '';
  }

  public function getId(): ?int
  {
    return $this->id;
  }

  public function getChampDisc(): ?Discipline
  {
    return $this->champDisc;
  }

  public function setChampDisc(?Discipline $champDisc): self
  {
    $this->champDisc = $champDisc;
    return $this;
  }

  public function getDiscipline(): ?Discipline
  {
    return $this->discipline;
  }

  public function setDiscipline(?Discipline $discipline): self
  {
    $this->discipline = $discipline;
    return $this;
  }

  public function getSpecialites(): Collection
  {
    return $this->specialites;
  }

  public function addSpecialite(Discipline $specialite): self
  {
    if (!$this->specialites->contains($specialite)) {
      $this->specialites->add($specialite);
    }
    return $this;
  }

  public function removeSpecialite(Discipline $specialite): self
  {
    $this->specialites->removeElement($specialite);
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
}
