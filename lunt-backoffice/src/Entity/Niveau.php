<?php

namespace App\Entity;

use App\Repository\NiveauRepository;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Classe représentant un niveau.
 */
#[ORM\Entity(repositoryClass: NiveauRepository::class)]
#[UniqueEntity('ordre', message: 'Cet ordre est déjà utilisé.')]
class Niveau
{
    use Timestamps;

    /**
     * Identifiant unique du niveau.
     *
     * @var int|null
     */
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    /**
     * Code unique du niveau.
     *
     * @var string|null
     */
    #[ORM\Column(length: 225, unique: true)]
    #[Assert\NotBlank]
    private ?string $code;

    /**
     * Nom du niveau.
     *
     * @var string|null
     */
    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    private ?string $nom;

    /**
     * Ordre d'affichage du niveau.
     *
     * @var int|null
     */
    #[ORM\Column(type: 'integer')]
    private ?int $ordre = null;

    /**
     * Constructeur de la classe Niveau.
     *
     * @param string|null $code
     * @param string|null $nom
     */
    public function __construct(?string $code = null, ?string $nom = null)
    {
        $this->creeLe = new DateTimeImmutable();
        $this->code = $code;
        $this->nom = $nom;
    }

    /**
     * Retourne l'identifiant du niveau.
     *
     * @return int|null
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * Retourne le code du niveau.
     *
     * @return string|null
     */
    public function getCode(): ?string
    {
        return $this->code;
    }

    /**
     * Défini le code du niveau.
     *
     * @param string $code
     * @return static
     */
    public function setCode(string $code): static
    {
        $this->code = $code;
        return $this;
    }

    /**
     * Retourne le nom du niveau.
     *
     * @return string|null
     */
    public function getNom(): ?string
    {
        return $this->nom;
    }

    /**
     * Défini le nom du niveau.
     *
     * @param string $nom
     * @return static
     */
    public function setNom(string $nom): static
    {
        $this->nom = $nom;
        return $this;
    }

    /**
     * Retourne l'ordre du niveau.
     *
     * @return int|null
     */
    public function getOrdre(): ?int
    {
        return $this->ordre;
    }

    /**
     * Défini l'ordre du niveau.
     *
     * @param int|null $ordre
     * @return void
     */
    public function setOrdre(?int $ordre): void
    {
        $this->ordre = $ordre;
    }

    /**
     * Retourne le nom du niveau en tant que chaîne de caractères.
     *
     * @return string
     */
    public function __toString(): string
    {
        return (string) $this->nom;
    }
}
