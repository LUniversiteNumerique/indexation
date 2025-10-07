<?php

namespace App\Entity;

use App\Entity\Dto\DeweyDto;
use App\Repository\DeweyRepository;
use Doctrine\Common\Collections\{ArrayCollection,Collection};
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: DeweyRepository::class)]
class Dewey
{
    use Timestamps;

    #[ORM\Column(length: 255, unique: true), Assert\NotBlank]
    private ?string $code;

    #[ORM\Column(length: 255), Assert\NotBlank]
    private ?string $nom;

    #[ORM\ManyToOne(targetEntity: self::class, fetch: 'EXTRA_LAZY', inversedBy: 'children'),
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

    public static function create(DeweyDto $dto): self
    {
        return new self($dto->uri, $dto->label);
    }

    public function getNumericCode(): ?string
    {
        // Remove the prefix
        $numericCode = str_replace('http://dewey.info/class/', '', $this->code);

        // Remove the final slash if present
        return rtrim($numericCode, "/");
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
        if ($this->getChildren()->count() > 0 ) {
            // Discipline ou division
            return $this->nom;
        } else {
            // Spécialité
            return $this->getNumericCode() . ' - ' . $this->nom;
        }
    }
}
