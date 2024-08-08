<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\{UserInterface,PasswordAuthenticatedUserInterface};

#[ORM\Table(name: '`user`'), UniqueEntity("email"),
    ORM\Entity(repositoryClass: UserRepository::class)]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    const ROLE_DEFAULT = 'ROLE_USER';

    use Timestamps;

    #[ORM\Column(length: 255),
        Assert\NotBlank, Assert\Type('string')]
    private ?string $name = null;

    #[ORM\Column(length: 180, unique: true),
        Assert\NotNull, Assert\Email]
    private ?string $email;

    #[ORM\Column(nullable: true)]
    private ?string $password;

    #[ORM\Column(nullable: true)]
    private ?bool $enabled = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $reseToken = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $tokenExpiresAt = null;

    #[ORM\ManyToOne(targetEntity: Groupe::class, inversedBy: 'users'),
        Assert\Valid, Assert\Type(Groupe::class)]
    private ?Groupe $group;

    #[ORM\ManyToOne, Assert\Valid,
        Assert\Type(Univerique::class)]
    private ?Univerique $untheme = null;

    #[ORM\ManyToOne, Assert\Valid,
        Assert\Type(Etablissement::class)]
    private ?Etablissement $school = null;
    private array $roles = [self::ROLE_DEFAULT];

    public function __construct(string $email=null, string $password=null, array $roles=[])
    {
        $this->email = $email;
        $this->password = $password;
        if(!empty($roles)) $this->roles = $roles;
        $this->creeLe = new \DateTimeImmutable();
    }

    public function getUserIdentifier(): string
    {
        return (string) $this->email;
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

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(?string $password): static
    {
        $this->password = $password;

        return $this;
    }

    public function isEnabled(): ?bool
    {
        return $this->enabled;
    }

    public function setEnabled(?bool $enabled): static
    {
        $this->enabled = $enabled;

        return $this;
    }

    public function getReseToken(): ?string
    {
        return $this->reseToken;
    }

    public function setReseToken(?string $token): static
    {
        $this->reseToken = $token;

        return $this;
    }

    public function getTokenExpiresAt(): ?\DateTimeImmutable
    {
        return $this->tokenExpiresAt;
    }

    public function setTokenExpiresAt(?\DateTimeImmutable $tokenExpiresAt): static
    {
        $this->tokenExpiresAt = $tokenExpiresAt;

        return $this;
    }

    public function getGroup(): ?Groupe
    {
        return $this->group;
    }

    public function setGroup(?Groupe $group): self
    {
        $this->group = $group;

        return $this;
    }

    public function getSchool(): ?Etablissement
    {
        return $this->school;
    }

    public function setSchool(?Etablissement $school): static
    {
        $this->school = $school;

        return $this;
    }

    public function getUntheme(): ?Univerique
    {
        return $this->untheme;
    }

    public function setUntheme(?Univerique $unt): static
    {
        $this->untheme = $unt;

        return $this;
    }

    public function getRoles(): array
    {
        $roles = $this->group ? array_merge($this->roles, $this->group->getRights()) : $this->roles;

        return array_values(array_unique($roles));
    }

    public function addRole($role): static
    {
        $role = strtoupper($role);
        if ($role === static::ROLE_DEFAULT) return $this;

        if (!in_array($role, $this->roles, true))
            $this->roles[] = $role;
        return $this;
    }

    public function removeRole($role): static
    {
        if (false !== $key = array_search(strtoupper($role), $this->roles, true)) {
            unset($this->roles[$key]);
            $this->roles = array_values($this->roles);
        }

        return $this;
    }

    public function eraseCredentials(): void {}

    public function __toString(): string
    {
        return $this->name;
    }
}
