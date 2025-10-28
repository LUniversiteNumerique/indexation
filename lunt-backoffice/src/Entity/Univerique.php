<?php

namespace App\Entity;

use App\Repository\UniveriqueRepository;
use Doctrine\Common\Collections\{ArrayCollection, Collection};
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: UniveriqueRepository::class),
    UniqueEntity('name'), UniqueEntity('label')]
class Univerique
{
    use Timestamps;

    #[ORM\Column(length: 255, unique: true),
        Assert\NotBlank, Assert\Type('string')]
    private ?string $label = null;

    #[ORM\Column(length: 255, unique: true),
        Assert\Regex('/^[a-zA-Z0-9-_]+$/', "Le nom du répertoire ne peut contenir que des caractères alphanumériques, un tiret ou un tiret bas.")]
    private ?string $name = null;

    #[ORM\Column(length: 255, nullable: true),
        Assert\Url, Assert\NotBlank]
    private ?string $siteWeb = null;

    #[ORM\ManyToMany(targetEntity: Discipline::class)]
    private Collection $fields;

    public function __construct()
    {
        $this->creeLe = new DateTimeImmutable();
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

    public function getSiteWeb(): ?string
    {
        return $this->siteWeb;
    }

    public function setSiteWeb(string $url): static
    {
        $this->siteWeb = $url;

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
