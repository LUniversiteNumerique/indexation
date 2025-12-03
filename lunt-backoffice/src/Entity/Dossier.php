<?php

namespace App\Entity;

use App\Repository\DossierRepository;
use Doctrine\Common\Collections\{ArrayCollection, Collection};
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: DossierRepository::class)]
class Dossier
{
    use Timestamps;

    #[ORM\Column(length: 255), Assert\NotBlank]
    private ?string $nom = null;

    #[ORM\ManyToOne] private ?User $user = null;

    #[ORM\ManyToOne(targetEntity: self::class, inversedBy: 'children'),
        Assert\Valid, Assert\Type(self::class)]
    private ?self $parent = null;

    #[ORM\OneToMany(mappedBy: 'parent', targetEntity: self::class)]
    private Collection $children;

    #[ORM\OneToMany(mappedBy: 'repertoire', targetEntity: Notice::class)]
    private Collection $notices;

    public function __construct()
    {
        $this->creeLe = new DateTimeImmutable();
        $this->children = new ArrayCollection();
        $this->notices = new ArrayCollection();
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

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): self
    {
        $this->user = $user;
        return $this;
    }

    public function getChildren(): Collection
    {
        return $this->children;
    }

    public function addChild(self $child): static
    {
        if (!$this->children->contains($child))
            $this->children->add($child->setParent($this));

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

    public function getParent(): ?self
    {
        return $this->parent;
    }

    public function setParent(?self $parent): static
    {
        $this->parent = $parent;

        return $this;
    }

    public function getNotices(): Collection
    {
        return $this->notices->filter(function (Notice $notice) {
            return !$notice->isDeleted();
        });
    }

    public function addNotice(Notice $notice): static
    {
        if (!$this->notices->contains($notice))
            $this->notices->add($notice->setRepertoire($this));

        return $this;
    }

    public function removeNotice(Notice $notice): static
    {
        if ($this->notices->removeElement($notice) && $notice->getRepertoire() === $this) {
            $notice->setRepertoire(null);
        }

        return $this;
    }

    public function __toString(): string
    {
        return $this->nom;
    }
}
