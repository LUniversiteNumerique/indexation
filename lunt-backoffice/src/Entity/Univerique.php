<?php

namespace App\Entity;

use App\Repository\UniveriqueRepository;
use Doctrine\Common\Collections\{ArrayCollection,Collection};
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UniveriqueRepository::class),
    UniqueEntity('name'), UniqueEntity('label')]
class Univerique
{
    use Timestamps;

    #[ORM\Column(length: 255, unique:true),
        Assert\NotBlank, Assert\Type('string')]
    private ?string $label = null;

    #[ORM\Column(length: 255, unique:true)]
    private ?string $name = null;

    #[ORM\ManyToMany(targetEntity: Discipline::class)]
    private Collection $fields;
    
    public function __construct()
    {
        $this->creeLe = new \DateTimeImmutable();
        $this->fields = new ArrayCollection();
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

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }
    public function getFields(): Collection
    {
        return $this->fields;
    }

    public function addField(Discipline $field): static
    {
        if (!$this->fields->contains($field)) {
            $this->fields->add($field);
        }

        return $this;
    }

    public function removeField(Discipline $field): static
    {
        $this->fields->removeElement($field);

        return $this;
    }

    public function __toString(): string
    {
        return $this->label;
    }
}
