<?php

namespace App\Entity;

use App\Repository\DisciplineRepository;
use Doctrine\Common\Collections\{ArrayCollection,Collection};
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: DisciplineRepository::class)]
class Discipline
{
    use Timestamps;

    #[ORM\Column(length: 255, unique: false), Assert\NotBlank]
    private ?string $code;

    #[ORM\Column(length: 255), Assert\NotBlank]
    private ?string $nom;

    #[ORM\ManyToOne(targetEntity: self::class, inversedBy: 'children'),
        Assert\Valid, Assert\Type(self::class)]
    private ?self $parent = null;

    #[ORM\OneToMany(mappedBy: 'parent', targetEntity: self::class)]
    private Collection $children;

    public function __construct($code=null,$nom=null)
    {
        $this->creeLe = new \DateTimeImmutable();
        $this->code = $code;
        $this->nom = $nom;
        $this->children = new ArrayCollection();
    }

    public static function create(array $o): self
    {
        return new self($o['id'],$o['libelle_uoh']);
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(?string $code): static
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

    public function getParent(): ?self
    {
        return $this->parent;
    }

    public function setParent(?self $parent): static
    {
        $this->parent = $parent;

        return $this;
    }

    public function getChildren(): Collection
    {
        return $this->children;
    }

    public function addChild(self $child): static
    {
        if (!$this->children->contains($child)) {
            $this->children->add($child);
            $child->setParent($this);
        }

        return $this;
    }

    public function removeChild(self $child): static
    {
        if ($this->children->removeElement($child)) {
            if ($child->getParent() === $this)
                $child->setParent(null);
        }

        return $this;
    }

    public function __toString(): string
    {
        return $this->nom;
    }
}
