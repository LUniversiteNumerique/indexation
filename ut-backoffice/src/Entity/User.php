<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\{Collection,ArrayCollection};
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\{UserInterface,PasswordAuthenticatedUserInterface};

#[ORM\Table(name: '`user`'),
    ORM\Entity(repositoryClass: UserRepository::class)]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    const ROLE_DEFAULT = 'ROLE_USER';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(length: 180, unique: true)]
    private ?string $email;

    #[ORM\Column] private ?string $password;

    #[ORM\ManyToOne(targetEntity: Groupe::class)]
    private ?Groupe $group;

    #[ORM\ManyToOne]
    private ?Etablissement $school = null;

    #[ORM\ManyToMany(targetEntity: Discipline::class)]
    private Collection $fields;
    private array $roles = [self::ROLE_DEFAULT];

    public function __construct(string $email=null, string $password=null, array $roles=[])
    {
        $this->email = $email;
        $this->password = $password;
        if(!empty($roles)) $this->roles = $roles;
        $this->fields = new ArrayCollection();
    }

    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

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

    /**
     * @return Collection<int, Discipline>
     */
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

    public function getRoles(): array
    {
        $roles = $this->group ? array_merge($this->roles, $this->group->getRights()) : $this->roles; //array_map(fn($value): string => Groupe::PERMISSIONS[$value],

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
