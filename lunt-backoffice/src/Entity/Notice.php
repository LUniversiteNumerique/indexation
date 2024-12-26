<?php

namespace App\Entity;

use App\Repository\NoticeRepository;
use Doctrine\Common\Collections\{ArrayCollection, Collection};
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\{Uid\Uuid,Validator\Constraints as Assert};

#[ORM\Entity(repositoryClass: NoticeRepository::class)]
class Notice
{
    use Timestamps;

    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $uuid;

    #[ORM\Column(length: 255), Assert\NotBlank]
    private ?string $titre = null;

    #[ORM\Column(type: Types::TEXT), Assert\NotNull]
    private ?string $description = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $label = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $vignette = null;

    #[ORM\Column(nullable: true)]
    private ?string $dureExec = null;

    #[ORM\Column(nullable: true)]
    private ?string $dureAppr = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $objectif = null;

    #[ORM\Column(nullable: true)]
    private ?array $propUser = null;

    #[ORM\Column(nullable: true)]
    private ?array $userLang = null;

    #[ORM\Column(nullable: true),
        Assert\Count(min: 1)]
    private ?array $ressLang = null;

    #[ORM\Column(nullable: true)]
    private ?int $ressSize = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $publieLe = null;

    #[ORM\Column(length: 10, nullable: true,
        enumType: NoticEtat::class)]
    private ?NoticEtat $etat;

    #[ORM\Column(nullable: true)]
    private ?bool $exportOAI = null;

    #[ORM\ManyToOne]
    private ?User $createur,$validateur;

    #[ORM\Column(length: 255)]
    private ?string $ressDate, $ressUrl = null;
    private ?string $ressZip = null;

    #[ORM\Column(length: 255, nullable: true),Assert\Url]
    private ?string $formEvalUrl = null;

    #[ORM\ManyToOne, Assert\NotNull,
        ORM\JoinColumn(nullable: false)]
    private ?Licence $droit = null;

    #[ORM\ManyToOne, Assert\Valid,
        Assert\Type(Dewey::class)]
    private ?Dewey $codewey = null;

    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false),
        Assert\Valid, Assert\Type(Discipline::class)]
    private ?Discipline $specialite = null;

    #[ORM\ManyToOne] private ?Dossier $repertoire = null;

    #[ORM\ManyToMany(targetEntity: Etablissement::class),
        Assert\Count(min: 1)]
    private Collection $porteurs;

    #[ORM\ManyToMany(targetEntity: Auteur::class, cascade: ['persist']),
        Assert\Count(min: 1)]
    private Collection $auteurs;

    #[ORM\ManyToMany(targetEntity: TDocument::class),
        Assert\Count(min: 1)]
    private Collection $docTypes;

    #[ORM\ManyToMany(targetEntity: TPedagogie::class),
        Assert\Count(min: 1)]
    private Collection $pedTypes;

    #[ORM\ManyToMany(targetEntity: Niveau::class),
        Assert\Count(min: 1)]
    private Collection $niveaux;

    #[ORM\ManyToMany(targetEntity: Keyword::class, cascade: ['persist']),
        Assert\Count(min: 1)]
    private Collection $tags;

    #[ORM\ManyToMany(targetEntity: self::class)]
    private Collection $ressources;

    #[ORM\Column]
    private ?bool $ressPayant = false, $proprIntel = false, $deleted = false;

    #[ORM\Column(nullable: true)]
    private ?bool $editDemande = null;

    #[ORM\Column(nullable: true)]
    private ?string $champExt1, $champExt2, $champExt3, $champExt4, $champExt5;

    public function __construct()
    {
        $this->uuid = Uuid::v4();
        $this->etat = NoticEtat::Working;
        $this->niveaux = new ArrayCollection();
        $this->docTypes = new ArrayCollection();
        $this->pedTypes = new ArrayCollection();
        $this->ressources = new ArrayCollection();
        $this->porteurs = new ArrayCollection();
        $this->auteurs = new ArrayCollection();
        $this->tags = new ArrayCollection();
        $this->creeLe = new \DateTimeImmutable();
    }

    public function getUuid(): ?Uuid
    {
        return $this->uuid;
    }

    public function setUuid(?Uuid $uuid): self
    {
        $this->uuid = $uuid;

        return $this;
    }

    public function getTitre(): ?string
    {
        return $this->titre;
    }

    public function setTitre(string $titre): self
    {
        $this->titre = $titre;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(?string $label): self
    {
        $this->label = $label;

        return $this;
    }

    public function getRessSize(): ?int
    {
        return $this->ressSize;
    }

    public function setRessSize(int $ressSize): self
    {
        $this->ressSize = $ressSize;

        return $this;
    }

    public function getDureExec(): ?string
    {
        return $this->dureExec;
    }

    public function setDureExec(?string $dureExec): self
    {
        $this->dureExec = $dureExec;

        return $this;
    }

    public function getDureAppr(): ?string
    {
        return $this->dureAppr;
    }

    public function setDureAppr(?string $dureAppr): self
    {
        $this->dureAppr = $dureAppr;

        return $this;
    }

    public function getPropUser(): ?array
    {
        return $this->propUser;
    }

    public function setPropUser(?array $propUser): self
    {
        $this->propUser = $propUser;

        return $this;
    }

    public function getVignette(): ?string
    {
        return $this->vignette;
    }

    public function setVignette(?string $vignette): self
    {
        $this->vignette = $vignette;

        return $this;
    }

    public function getObjectif(): ?string
    {
        return $this->objectif;
    }

    public function setObjectif(?string $objectif): self
    {
        $this->objectif = $objectif;

        return $this;
    }

    public function getUserLang(): ?array
    {
        return $this->userLang;
    }

    public function setUserLang(?array $userLang): self
    {
        $this->userLang = $userLang;

        return $this;
    }

    public function getRessLang(): ?array
    {
        return $this->ressLang;
    }

    public function setRessLang(?array $ressLang): self
    {
        $this->ressLang = $ressLang;

        return $this;
    }

    public function getPublieLe(): ?\DateTimeInterface
    {
        return $this->publieLe;
    }

    public function setPublieLe(?\DateTimeInterface $publieLe): self
    {
        $this->publieLe = $publieLe;

        return $this;
    }

    public function getEtat(): ?NoticEtat
    {
        return $this->etat;
    }

    public function setEtat(?NoticEtat $etat): self
    {
        $this->etat = $etat;

        return $this;
    }

    public function isExportOAI(): ?bool
    {
        return $this->exportOAI;
    }

    public function setExportOAI(?bool $exportOAI): self
    {
        $this->exportOAI = $exportOAI;

        return $this;
    }

    public function getRessDate(): ?string
    {
        return $this->ressDate;
    }
    public function setRessDate(?string $ressDate): self
    {
        $this->ressDate = $ressDate;

        return $this;
    }

    public function getRessUrl(): ?string
    {
        return $this->ressUrl;
    }

    public function setRessUrl(?string $url): self
    {
        $this->ressUrl = $url;

        return $this;
    }

    public function getRessZip(): ?string
    {
        return $this->ressZip;
    }

    public function setRessZip(?string $zip): self
    {
        $this->ressZip = $zip;

        return $this;
    }

    public function getFormEvalUrl(): ?string
    {
        return $this->formEvalUrl;
    }

    public function setFormEvalUrl(?string $formEvalUrl): static
    {
        $this->formEvalUrl = $formEvalUrl;

        return $this;
    }

    public function getCreateur(): ?User
    {
        return $this->createur;
    }

    public function setCreateur(?User $createur): self
    {
        $this->createur = $createur;

        return $this;
    }

    public function getValidateur(): ?User
    {
        return $this->validateur;
    }

    public function setValidateur(?User $validateur): self
    {
        $this->validateur = $validateur;

        return $this;
    }

    public function getDroit(): ?Licence
    {
        return $this->droit;
    }

    public function setDroit(?Licence $droit): self
    {
        $this->droit = $droit;

        return $this;
    }

    public function getCodewey(): ?Dewey
    {
        return $this->codewey;
    }

    public function setCodewey(?Dewey $codewey): self
    {
        $this->codewey = $codewey;

        return $this;
    }

    public function getSpecialite(): ?Discipline
    {
        return $this->specialite;
    }

    public function setSpecialite(?Discipline $specialite): self
    {
        $this->specialite = $specialite;

        return $this;
    }

    public function getRepertoire(): ?Dossier
    {
      return $this->repertoire;
    }

    public function setRepertoire(?Dossier $repertoire): static
    {
      $this->repertoire = $repertoire;

      return $this;
    }

    /**
     * @return Collection<int, Niveau>
     */
    public function getNiveaux(): Collection
    {
        return $this->niveaux;
    }

    public function addNiveau(Niveau $niveau): self
    {
        if (!$this->niveaux->contains($niveau)) {
            $this->niveaux->add($niveau);
        }

        return $this;
    }

    public function removeNiveau(Niveau $niveau): self
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

    public function addDocType(TDocument $docType): self
    {
        if (!$this->docTypes->contains($docType)) {
            $this->docTypes->add($docType);
        }

        return $this;
    }

    public function removeDocType(TDocument $docType): self
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

    public function addPedType(TPedagogie $pedType): self
    {
        if (!$this->pedTypes->contains($pedType)) {
            $this->pedTypes->add($pedType);
        }

        return $this;
    }

    public function removePedType(TPedagogie $pedType): self
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

    public function addPorteur(Etablissement $porteur): self
    {
        if (!$this->porteurs->contains($porteur)) {
            $this->porteurs->add($porteur);
        }

        return $this;
    }

    public function removePorteur(Etablissement $porteur): self
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

    public function addTag(Keyword $tag): self
    {
        if (!$this->tags->contains($tag)) {
            $this->tags->add($tag);
        }

        return $this;
    }

    public function removeTag(Keyword $tag): self
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

    public function addRessource(self $ressource): self
    {
        if (!$this->ressources->contains($ressource)) {
            $this->ressources->add($ressource);
        }

        return $this;
    }

    public function removeRessource(self $ressource): self
    {
        $this->ressources->removeElement($ressource);

        return $this;
    }

    public function isRessPayant(): ?bool
    {
        return $this->ressPayant;
    }

    public function setRessPayant(bool $ressPayant): static
    {
        $this->ressPayant = $ressPayant;

        return $this;
    }

    public function isProprIntel(): ?bool
    {
        return $this->proprIntel;
    }

    public function setProprIntel(bool $proprIntel): static
    {
        $this->proprIntel = $proprIntel;

        return $this;
    }

    public function isDeleted(): ?bool
    {
        return $this->deleted;
    }

    public function setDeleted(bool $deleted): static
    {
        $this->deleted = $deleted;

        return $this;
    }

    public function isEditDemande(): ?bool
    {
        return $this->editDemande;
    }

    public function setEditDemande(?bool $editDemande): static
    {
        $this->editDemande = $editDemande;

        return $this;
    }

    public function getChampExt1(): ?string
    {
        return $this->champExt1;
    }

    public function setChampExt1(?string $champExt1): static
    {
        $this->champExt1 = $champExt1;

        return $this;
    }

    public function getChampExt2(): ?string
    {
        return $this->champExt2;
    }

    public function setChampExt2(?string $champExt2): static
    {
        $this->champExt2 = $champExt2;

        return $this;
    }

    public function getChampExt3(): ?string
    {
        return $this->champExt3;
    }

    public function setChampExt3(?string $champExt3): static
    {
        $this->champExt3 = $champExt3;

        return $this;
    }

    public function getChampExt4(): ?string
    {
        return $this->champExt4;
    }

    public function setChampExt4(?string $champExt4): static
    {
        $this->champExt4 = $champExt4;

        return $this;
    }

    public function getChampExt5(): ?string
    {
        return $this->champExt5;
    }

    public function setChampExt5(?string $champExt5): static
    {
        $this->champExt5 = $champExt5;

        return $this;
    }

    public function __toString(): string
    {
        return sprintf('%s', $this->titre);
    }

    /**
     * Checks if the current Notice belongs to a specified Univerique.
     *
     * A Univerique is defined by a set of disciplines ("fields"). 
     * This method determines whether the grandparent discipline of the current 
     * Notice's speciality is part of the Univerique's fields.
     *
     * @param Univerique $univerique The Univerique to check against.
     * @return bool True if the Notice belongs to the Univerique, false otherwise.
     */
    public function belongsToUniverique(Univerique $univerique): bool
    {
         // Convert PersistentCollection to an array
        $fields = $univerique->getFields()->toArray();

        // Extract IDs of the fields (disciplines) associated with the Univerique
        $univeriqueFieldIds = array_map(
            fn($discipline) => $discipline->getId(),
            $fields
        );

        // Get the ID of the grandparent discipline of the current Notice's speciality
        $currentGrandParentId = $this->getSpecialite()?->getParent()?->getParent()?->getId();

        // Check if the grandparent discipline is part of the Univerique's fields
        return in_array($currentGrandParentId, $univeriqueFieldIds, true);
    }
}
