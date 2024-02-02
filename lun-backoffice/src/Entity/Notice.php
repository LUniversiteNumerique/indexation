<?php

namespace App\Entity;

use App\Repository\NoticeRepository;
use Doctrine\Common\Collections\{ArrayCollection, Collection};
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: NoticeRepository::class)]
class Notice
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255), Assert\NotBlank]
    private ?string $titre = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $label = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $dewey = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $vignette = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $objectif = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $propUser = null;

    #[ORM\Column(nullable: true)]
    private ?int $taille = null;

    #[ORM\Column(nullable: true)]
    private ?int $dureExec = null;

    #[ORM\Column(nullable: true)]
    private ?int $dureAppr = null;

    #[ORM\Column(nullable: true)]
    private ?array $ressLang = null;

    #[ORM\Column(nullable: true)]
    private ?array $userLang = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $creeLe;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $modifieLe = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $publieLe = null;

    #[ORM\Column(length: 10, nullable: true, enumType: NoticEtat::class)]
    private ?NoticEtat $etat;

    #[ORM\Column(nullable: true)]
    private ?bool $exportOAI = null;

    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\Embedded(Ressource::class),
        Assert\Valid, Assert\Type(Ressource::class)]
    private ?Ressource $contenu;

    #[ORM\ManyToOne, Assert\NotNull]
    private ?Licence $droit = null;

    #[ORM\ManyToOne, Assert\NotNull]
    private ?Discipline $specialite = null;

    #[ORM\ManyToMany(targetEntity: Etablissement::class)]
    private Collection $porteurs;

    #[ORM\ManyToMany(targetEntity: Auteur::class, cascade: ['persist'])]
    private Collection $auteurs;

    #[ORM\ManyToMany(targetEntity: TDocument::class)]
    private Collection $docTypes;

    #[ORM\ManyToMany(targetEntity: TPedagogie::class)]
    private Collection $pedTypes;

    #[ORM\ManyToMany(targetEntity: Niveau::class)]
    private Collection $niveaux;

    #[ORM\ManyToMany(targetEntity: Keyword::class, cascade: ['persist'])]
    private Collection $tags;

    #[ORM\ManyToMany(targetEntity: self::class)]
    private Collection $ressources;

    public function __construct()
    {
        $this->contenu = new Ressource();
        $this->niveaux = new ArrayCollection();
        $this->docTypes = new ArrayCollection();
        $this->pedTypes = new ArrayCollection();
        $this->ressources = new ArrayCollection();
        $this->porteurs = new ArrayCollection();
        $this->auteurs = new ArrayCollection();
        $this->tags = new ArrayCollection();
        $this->etat = NoticEtat::Working;
        $this->creeLe = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitre(): ?string
    {
        return $this->titre;
    }

    public function setTitre(string $titre): static
    {
        $this->titre = $titre;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;

        return $this;
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

    public function getDewey(): ?string
    {
        return $this->dewey;
    }

    public function setDewey(?string $dewey): static
    {
        $this->dewey = $dewey;

        return $this;
    }

    public function getTaille(): ?int
    {
        return $this->taille;
    }

    public function setTaille(int $taille): static
    {
        $this->taille = $taille;

        return $this;
    }

    public function getDureExec(): ?int
    {
        return $this->dureExec;
    }

    public function setDureExec(?int $dureExec): static
    {
        $this->dureExec = $dureExec;

        return $this;
    }

    public function getDureAppr(): ?int
    {
        return $this->dureAppr;
    }

    public function setDureAppr(?int $dureAppr): static
    {
        $this->dureAppr = $dureAppr;

        return $this;
    }

    public function getPropUser(): ?string
    {
        return $this->propUser;
    }

    public function setPropUser(?string $propUser): static
    {
        $this->propUser = $propUser;

        return $this;
    }

    public function getVignette(): ?string
    {
        return $this->vignette;
    }

    public function setVignette(?string $vignette): static
    {
        $this->vignette = $vignette;

        return $this;
    }

    public function getObjectif(): ?string
    {
        return $this->objectif;
    }

    public function setObjectif(?string $objectif): static
    {
        $this->objectif = $objectif;

        return $this;
    }

    public function getRessLang(): ?array
    {
        return $this->ressLang;
    }

    public function setRessLang(?array $ressLang): static
    {
        $this->ressLang = $ressLang;

        return $this;
    }

    public function getUserLang(): ?array
    {
        return $this->userLang;
    }

    public function setUserLang(?array $userLang): static
    {
        $this->userLang = $userLang;

        return $this;
    }

    public function getCreeLe(): ?\DateTimeInterface
    {
        return $this->creeLe;
    }

    public function setCreeLe(\DateTimeInterface $creeLe): static
    {
        $this->creeLe = $creeLe;

        return $this;
    }

    public function getModifieLe(): ?\DateTimeInterface
    {
        return $this->modifieLe;
    }

    public function setModifieLe(?\DateTimeInterface $modifieLe): static
    {
        $this->modifieLe = $modifieLe;

        return $this;
    }

    public function getPublieLe(): ?\DateTimeInterface
    {
        return $this->publieLe;
    }

    public function setPublieLe(?\DateTimeInterface $publieLe): static
    {
        $this->publieLe = $publieLe;

        return $this;
    }

    public function getEtat(): ?NoticEtat
    {
        return $this->etat;
    }

    public function setEtat(?NoticEtat $etat): static
    {
        $this->etat = $etat;

        return $this;
    }

    public function isExportOAI(): ?bool
    {
        return $this->exportOAI;
    }

    public function setExportOAI(?bool $exportOAI): static
    {
        $this->exportOAI = $exportOAI;

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getContenu(): ?Ressource
    {
        return $this->contenu;
    }

    public function setContenu(?Ressource $contenu): static
    {
        $this->contenu = $contenu;

        return $this;
    }

    public function getDroit(): ?Licence
    {
        return $this->droit;
    }

    public function setDroit(?Licence $droit): static
    {
        $this->droit = $droit;

        return $this;
    }

    public function getSpecialite(): ?Discipline
    {
        return $this->specialite;
    }

    public function setSpecialite(?Discipline $specialite): static
    {
        $this->specialite = $specialite;

        return $this;
    }

    /**
     * @return Collection<int, Niveau>
     */
    public function getNiveaux(): Collection
    {
        return $this->niveaux;
    }

    public function addNiveau(Niveau $niveau): static
    {
        if (!$this->niveaux->contains($niveau)) {
            $this->niveaux->add($niveau);
        }

        return $this;
    }

    public function removeNiveau(Niveau $niveau): static
    {
        $this->niveaux->removeElement($niveau);

        return $this;
    }

    /**
     * @return Collection<int, TDocument>
     */
    public function getDocTypes(): Collection
    {
        return $this->docTypes;
    }

    public function addDocType(TDocument $docType): static
    {
        if (!$this->docTypes->contains($docType)) {
            $this->docTypes->add($docType);
        }

        return $this;
    }

    public function removeDocType(TDocument $docType): static
    {
        $this->docTypes->removeElement($docType);

        return $this;
    }

    /**
     * @return Collection<int, TPedagogie>
     */
    public function getPedTypes(): Collection
    {
        return $this->pedTypes;
    }

    public function addPedType(TPedagogie $pedType): static
    {
        if (!$this->pedTypes->contains($pedType)) {
            $this->pedTypes->add($pedType);
        }

        return $this;
    }

    public function removePedType(TPedagogie $pedType): static
    {
        $this->pedTypes->removeElement($pedType);

        return $this;
    }

    /**
     * @return Collection<int, Etablissement>
     */
    public function getPorteurs(): Collection
    {
        return $this->porteurs;
    }

    public function addPorteur(Etablissement $porteur): static
    {
        if (!$this->porteurs->contains($porteur)) {
            $this->porteurs->add($porteur);
        }

        return $this;
    }

    public function removePorteur(Etablissement $porteur): static
    {
        $this->porteurs->removeElement($porteur);

        return $this;
    }

    /**
     * @return Collection<int, Auteur>
     */
    public function getAuteurs(): Collection
    {
        return $this->auteurs;
    }

    public function addAuteur(Auteur $auteur): static
    {
        if (!$this->auteurs->contains($auteur)) {
            $this->auteurs->add($auteur);
        }

        return $this;
    }

    public function removeAuteur(Auteur $auteur): static
    {
        $this->auteurs->removeElement($auteur);

        return $this;
    }

    /**
     * @return Collection<int, Keyword>
     */
    public function getTags(): Collection
    {
        return $this->tags;
    }

    public function addTag(Keyword $tag): static
    {
        if (!$this->tags->contains($tag)) {
            $this->tags->add($tag);
        }

        return $this;
    }

    public function removeTag(Keyword $tag): static
    {
        $this->tags->removeElement($tag);

        return $this;
    }

    /**
     * @return Collection<int, self>
     */
    public function getRessources(): Collection
    {
        return $this->ressources;
    }

    public function addRessource(self $ressource): static
    {
        if (!$this->ressources->contains($ressource)) {
            $this->ressources->add($ressource);
        }

        return $this;
    }

    public function removeRessource(self $ressource): static
    {
        $this->ressources->removeElement($ressource);

        return $this;
    }

    public function __toString(): string
    {
        return sprintf('%d#%s', $this->id, $this->titre);
    }
}
