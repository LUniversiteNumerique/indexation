<?php

namespace App\Entity;

use App\Repository\GroupeRepository;
use Doctrine\Common\Collections\{ArrayCollection,Collection};
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: GroupeRepository::class)]
class Groupe
{
    const PERMISSIONS = array(
        'Lire_Auteur'=>'ROLE_READ_ACTE', 'Créer_Auteur'=>'ROLE_CREA_ACTE', 'Editer_Auteur'=>'ROLE_EDIT_ACTE', 'Supprimer_Auteur'=>'ROLE_DROP_ACTE',
        'Lire_Repert'=>'ROLE_READ_DOSS', 'Créer_Repert'=>'ROLE_CREA_DOSS', 'Editer_Repert'=>'ROLE_EDIT_DOSS', 'Supprimer_Repert'=>'ROLE_DROP_DOSS',
        'Lire_TPedag'=>'ROLE_READ_TPED', 'Créer_TPedag'=>'ROLE_CREA_TPED', 'Editer_TPedag'=>'ROLE_EDIT_TPED', 'Supprimer_TPedag'=>'ROLE_DROP_TPED',
        'Lire_Licenc'=>'ROLE_READ_LICE', 'Créer_Licenc'=>'ROLE_CREA_LICE', 'Editer_Licenc'=>'ROLE_EDIT_LICE', 'Supprimer_Licenc'=>'ROLE_DROP_LICE',
        'Lire_Univer'=>'ROLE_READ_UNIV', 'Créer_Univer'=>'ROLE_CREA_UNIV', 'Editer_Univer'=>'ROLE_EDIT_UNIV', 'Supprimer_Univer'=>'ROLE_DROP_UNIV',
        'Lire_Etabli'=>'ROLE_READ_ETAB', 'Créer_Etabli'=>'ROLE_CREA_ETAB', 'Editer_Etabli'=>'ROLE_EDIT_ETAB', 'Supprimer_Etabli'=>'ROLE_DROP_ETAB',
        'Lire_Groupe'=>'ROLE_READ_GROU', 'Créer_Groupe'=>'ROLE_CREA_GROU', 'Editer_Groupe'=>'ROLE_EDIT_GROU', 'Supprimer_Groupe'=>'ROLE_DROP_GROU',
        'Lire_MotClé'=>'ROLE_READ_KEYW', 'Créer_MotClé'=>'ROLE_CREA_KEYW', 'Editer_MotClé'=>'ROLE_EDIT_KEYW', 'Supprimer_MotClé'=>'ROLE_DROP_KEYW',
        'Lire_Utilis'=>'ROLE_READ_USER', 'Créer_Utilis'=>'ROLE_CREA_USER', 'Editer_Utilis'=>'ROLE_EDIT_USER', 'Supprimer_Utilis'=>'ROLE_DROP_USER',
        'Lire_Notice'=>'ROLE_READ_NOTI', 'Créer_Notice'=>'ROLE_CREA_NOTI', 'Editer_Notice'=>'ROLE_EDIT_NOTI', 'Supprimer_Notice'=>'ROLE_DROP_NOTI', 'Valider_Notice'=>'ROLE_VALI_NOTI',
        'Lire_Indexe'=>'ROLE_READ_CORE', 'Créer_Indexe'=>'ROLE_CREA_CORE', 'Editer_Indexe'=>'ROLE_EDIT_CORE', 'Supprimer_Indexe'=>'ROLE_DROP_CORE',
        'Lire_Public'=>'ROLE_READ_NIVE', 'Créer_Public'=>'ROLE_CREA_NIVE', 'Editer_Public'=>'ROLE_EDIT_NIVE', 'Supprimer_Public'=>'ROLE_DROP_NIVE',
        'Lire_TDocum'=>'ROLE_READ_TDOC', 'Créer_TDocum'=>'ROLE_CREA_TDOC', 'Editer_TDocum'=>'ROLE_EDIT_TDOC', 'Supprimer_TDocum'=>'ROLE_DROP_TDOC',
    );

    use Timestamps;

    #[ORM\Column(length: 255),
        Assert\NotBlank, Assert\Type('string')]
    private ?string $label = null;

    #[ORM\Column, Assert\Count(min: 1),
    Assert\Choice(choices: self::PERMISSIONS, multiple: true)]
    private array $rights = [];

    #[ORM\OneToMany(mappedBy: 'group', targetEntity: User::class)]
    private Collection $users;

    public function __construct()
    {
        $this->users = new ArrayCollection();
        $this->creeLe = new \DateTimeImmutable();
    }

    public function hasRight($name): bool
    {
        return in_array($name, $this->getRights(),true);
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(?string $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function getRights(): array
    {
        return $this->rights;
    }

    public function setRights(array $rights): static
    {
        $this->rights = $rights;

        return $this;
    }

    public function getUsers(): Collection
    {
        return $this->users;
    }

    public function addUser(User $user): self
    {
        if (!$this->users->contains($user))
            $this->users->add($user->setGroup($this));

        return $this;
    }

    public function removeUser(User $user): self
    {
        if ($this->users->removeElement($user)) {
            if ($user->getGroup() === $this)
                $user->setGroup(null);
        }

        return $this;
    }

    public function __toString(): string
    {
        return $this->label;
    }
}
