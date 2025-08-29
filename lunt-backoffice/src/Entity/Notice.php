<?php

namespace App\Entity;

use App\Repository\NoticeRepository;
use Doctrine\Common\Collections\{ArrayCollection, Collection};
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\{Uid\Uuid,Validator\Constraints as Assert};
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity(repositoryClass: NoticeRepository::class)]
class Notice
{
    use Timestamps;

    #[ORM\Column(type: "string", length: 128, unique: true)]
    private ?string $uuid = null;

    #[ORM\Column(length: 285, nullable: true)]
    #[Assert\NotNull(message: 'La notice doit contenir un titre.')]
    private ?string $titre = null;

  #[ORM\Column(length: 285, nullable: true)]
  #[Assert\NotNull(message: 'La notice doit contenir un Contenu Url.')]
  private ?string $ressUrl = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\NotNull(message: 'La notice doit contenir une desciption.')]
    private ?string $description = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $label = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $vignette = null;

    #[ORM\Column(nullable: true)]
    private ?string $dureExec = null;

    #[ORM\Column(nullable: true)]
    private ?string $dureAppr = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $objectif = null;

    #[ORM\Column(nullable: true)]
    private ?array $propUser = null;

    #[ORM\Column(nullable: true)]
    private ?array $userLang = null;

    #[ORM\Column(nullable: true), Assert\Count(min: 1, minMessage: 'La notice doit contenir au moins une langue de la ressource.')]
    private ?array $ressLang = null;

    #[ORM\Column(nullable: true)]
    private ?float $ressSize = null;

    #[ORM\Column(length: 255, nullable: true),Assert\NotNull(message: 'La notice doit contenir une année de création.')]
    private ?int $ressDate = null;
    private ?string $ressZip = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $publieLe = null;

    #[ORM\Column(length: 10, nullable: true,
        enumType: NoticEtat::class)]
    private ?NoticEtat $etat;

    #[ORM\Column(nullable: true)]
    private ?bool $exportOAI = true;

    #[ORM\ManyToOne]
    private ?User $createur,$validateur;

    #[ORM\Column(length: 255, nullable: true),Assert\Url]
    private ?string $formEvalUrl = null;

    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull(message: 'La notice doit contenir une Licence et conditions d\'utilisation.')]
    private ?Licence $droit = null;

    #[ORM\ManyToOne] private ?Dossier $repertoire = null;

    #[ORM\ManyToMany(targetEntity: Dewey::class)]
    private Collection $codeweys;

    #[ORM\ManyToMany(targetEntity: Discipline::class)]
    private Collection $specialites;

    #[ORM\ManyToMany(targetEntity: Etablissement::class), Assert\Count(min: 1, minMessage: 'La notice doit contenir au moins un établissement porteur.')]
    private Collection $porteurs;

    #[ORM\ManyToMany(targetEntity: Auteur::class, cascade: ['persist']), Assert\Count(min: 1, minMessage: 'La notice doit contenir au moins un auteur.')]
    private Collection $auteurs;

    #[ORM\ManyToMany(targetEntity: TDocument::class), Assert\Count(min: 1, minMessage: 'La notice doit contenir au moins un type documentaire.')]
    private Collection $docTypes;

    #[ORM\ManyToMany(targetEntity: TPedagogie::class), Assert\Count(min: 1, minMessage: 'La notice doit contenir au moins un type pédagogique.')]
    private Collection $pedTypes;

    #[ORM\ManyToMany(targetEntity: Niveau::class), Assert\Count(min: 1, minMessage: 'La notice doit contenir au moins un niveau du public cible.')]
    private Collection $niveaux;

    #[ORM\ManyToMany(targetEntity: Keyword::class, cascade: ['persist']), Assert\Count(min: 1, minMessage: 'La notice doit contenir au moins un mots-clé.')]
    private Collection $tags;

    #[ORM\ManyToMany(targetEntity: self::class, cascade: ['all'])]
    private Collection $ressources;

    #[ORM\Column]
    private ?bool $ressPayant = false, $proprIntel = false, $deleted = false, $editDemande = false;

    #[ORM\Column(nullable: true)]
    private ?string $champExt1, $champExt2, $champExt3, $champExt4, $champExt5;
    #[ORM\ManyToMany(targetEntity: DeweyPerso::class)]
    private Collection $deweyPersos;

    #[ORM\OneToMany(mappedBy: 'notice', targetEntity: DisciplineGroup::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[Assert\Count(min: 1, minMessage: 'La notice doit contenir au moins une sous-discipline.')]
    #[Assert\Valid]
    private Collection $disciplineGroups;

    #[ORM\OneToMany(mappedBy: 'notice', targetEntity: DeweyGroup::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[Assert\Valid]
    private Collection $deweyGroups;

    public function __construct()
    {
        $this->uuid = Uuid::v4();
        $this->etat = NoticEtat::Working;
        $this->niveaux = new ArrayCollection();
        $this->docTypes = new ArrayCollection();
        $this->pedTypes = new ArrayCollection();
        $this->ressources = new ArrayCollection();
        $this->specialites = new ArrayCollection();
        $this->codeweys = new ArrayCollection();
        $this->porteurs = new ArrayCollection();
        $this->auteurs = new ArrayCollection();
        $this->tags = new ArrayCollection();
        $this->creeLe = new \DateTimeImmutable();
        $this->deweyPersos = new ArrayCollection();
        $this->disciplineGroups = new ArrayCollection();
        $this->deweyGroups = new ArrayCollection();
    }

    public function getUuid(): ?string
    {
        return $this->uuid;
    }

    public function setUuid(?string $uuid): self
    {
        $this->uuid = $uuid;

        return $this;
    }

    public function getTitre(): ?string
    {
        return $this->titre;
    }

    public function setTitre(?string $titre): self
    {
        $this->titre = $titre;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
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

    public function getRessSize(): ?float
    {
        return $this->ressSize;
    }

    public function setRessSize(?float $ressSize): self
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

    public function getRessDate(): ?int
    {
        return $this->ressDate;
    }
    public function setRessDate(?int $ressDate): self
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
     * @return Collection<int, Dewey>
     */
    public function getCodeweys(): Collection
    {
        return $this->codeweys;
    }

    public function addCodewey(Dewey $dewey): self
    {
        if (!$this->codeweys->contains($dewey)) {
            $this->codeweys->add($dewey);
        }

        return $this;
    }

    public function removeCodewey(Dewey $dewey): self
    {
        $this->codeweys->removeElement($dewey);

        return $this;
    }

    /**
     * @return Collection<int, Discipline>
     */
    public function getSpecialites(): Collection
    {
        return $this->specialites;
    }

    public function addSpecialite(Discipline $specialite): self
    {
        if (!$this->specialites->contains($specialite)) {
            $this->specialites->add($specialite);
        }

        return $this;
    }

    public function removeSpecialite(Discipline $specialite): self
    {
        $this->specialites->removeElement($specialite);

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

    public function setRessPayant(?bool $ressPayant): static
    {
        $this->ressPayant = $ressPayant;

        return $this;
    }

    public function isProprIntel(): ?bool
    {
        return $this->proprIntel;
    }

    public function setProprIntel(?bool $proprIntel): static
    {
        $this->proprIntel = $proprIntel;

        return $this;
    }

    public function isDeleted(): ?bool
    {
        return $this->deleted;
    }

    public function setDeleted(?bool $deleted): static
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

    public function getDeweyPersos(): Collection
    {
      return $this->deweyPersos;
    }

    public function addDeweyPerso(DeweyPerso $deweyPerso): static
    {
      if (!$this->deweyPersos->contains($deweyPerso)) {
        $this->deweyPersos->add($deweyPerso);
      }
      return $this;
    }

    public function removeDeweyPerso(DeweyPerso $deweyPerso): static
    {
      $this->deweyPersos->removeElement($deweyPerso);
      return $this;
    }
    /**
   * @return Collection<int, DisciplineGroup>
   */
    public function getDisciplineGroups(): Collection
    {
      return $this->disciplineGroups;
    }

    public function addDisciplineGroup(DisciplineGroup $disciplineGroup): self
    {
      if (!$this->disciplineGroups->contains($disciplineGroup)) {
        $this->disciplineGroups->add($disciplineGroup);
        $disciplineGroup->setNotice($this);
      }

      return $this;
    }

    public function removeDisciplineGroup(DisciplineGroup $disciplineGroup): self
    {
      if ($this->disciplineGroups->removeElement($disciplineGroup)) {
        // set the owning side to null (unless already changed)
        if ($disciplineGroup->getNotice() === $this) {
          $disciplineGroup->setNotice(null);
        }
      }

      return $this;
    }

    public function setDeweyGroups(Collection $groups): self
    {
      $this->deweyGroups = $groups;
      return $this;
    }

    public function getDeweyGroups(): Collection
    {
      return $this->deweyGroups;
    }
    public function addDeweyGroup(DeweyGroup $group): self
    {
      if (!$this->deweyGroups->contains($group)) {
        $this->deweyGroups->add($group);
        $group->setNotice($this);
      }
      return $this;
    }
    public function removeDeweyGroup(DeweyGroup $group): self
    {
      if ($this->deweyGroups->removeElement($group)) {
        if ($group->getNotice() === $this) {
          $group->setNotice(null);
        }
      }
      return $this;
    }

    public function __toString(): string
    {
        return sprintf('%s', $this->titre);
    }

    /**
     * Checks if the current Notice belongs to a specified Univerique.
     *
     * An Univerique is defined by a set of disciplines ("fields").
     * This method determines whether the grandparent discipline of the current
     * Notice's speciality is part of the Univerique's fields.
     *
     * @param Univerique $unt
     * @return bool True if the Notice belongs to the Univerique, false otherwise.
     */
    public function belongsToUNT(Univerique $unt): bool
    {
      if($this->getDisciplineGroups()->isEmpty()) return false;

      // Extract IDs of the fields (disciplines) associated with the Univerique
      $untFieldIds = $unt->getFields()->map(fn($discipline) => $discipline->getId());

      // Check if any disciplineGroup's champDisc is part of the Univerique's fields
      foreach ($this->getDisciplineGroups() as $group) {
        if ($group->getChampDisc() && $untFieldIds->contains($group->getChampDisc()->getId())) {
          return true;
        }
      }

      return false;
    }

  /**
   * Retrieves all specialities associated with the Notice, including those from its DisciplineGroups.
   *
   * This method aggregates specialities from both the Notice's direct specialities
   * and those from its DisciplineGroups, ensuring no duplicates.
   *
   * @return Discipline[] An array of unique specialities associated with the Notice.
   */
  public function getAllSpecialites(): array
  {
    $specialites = [];
    foreach ($this->getDisciplineGroups() as $group) {
      foreach ($group->getSpecialites() as $spec) {
        if (!in_array($spec, $specialites, true)) {
          $specialites[] = $spec;
        }
      }
    }
    return $specialites;
  }

  /**
   * Returns a string representation of all specialities associated with the Notice.
   *
   * Each speciality is wrapped in a badge for styling purposes.
   * If no specialities are found, a default message is returned.
   *
   * @return string A string of badges representing the specialities or a default message.
   */
    public function getAllSpecialitesString(): string
    {
      $specs = $this->getAllSpecialites();
      if (!$specs) return '<span class="badge bg-secondary">Aucune</span>';
      return implode('<br>', array_map(fn($s) => '<span class="badge badge-custom">'.$s->getNom().'</span>', $specs));
    }


    public function getAllDewey(): array
    {
        $codeweys = [];
        // Ajoute les Dewey classiques via les groupes
        foreach ($this->getDeweyGroups() as $group) {
            foreach ($group->getCodeweys() as $code) {
                if (!in_array($code, $codeweys, true)) {
                    $codeweys[] = $code;
                }
            }
        }
        // Ajoute les DeweyPerso sélectionnés
        foreach ($this->getDeweyPersos() as $perso) {
            if (!in_array($perso, $codeweys, true)) {
                $codeweys[] = $perso;
            }
        }
        return $codeweys;
    }

    public function getAllDeweyString(): string
    {
        $codeDew = $this->getAllDewey();
        if (!$codeDew) return '<span class="badge bg-secondary">Aucune</span>';
        return implode('<br>', array_map(fn($s) => '<span class="badge badge-custom">'.$s->getNom().'</span>', $codeDew));
    }
    public function getAllDeweyGroup(): array
    {
      $codeweys = [];
      // Ajoute les Dewey classiques via les groupes
      foreach ($this->getDeweyGroups() as $group) {
        foreach ($group->getCodeweys() as $code) {
          if (!in_array($code, $codeweys, true)) {
            $codeweys[] = $code;
          }
        }
      }
      return $codeweys;
    }
    public function getAllDeweyPerso(): array
    {
      $codeweys = [];
      // Ajoute les DeweyPerso sélectionnés
      foreach ($this->getDeweyPersos() as $perso) {
        if (!in_array($perso, $codeweys, true)) {
          $codeweys[] = $perso;
        }
      }
      return $codeweys;
    }

    #[Assert\Callback]
    public function validateDeweyGroups(ExecutionContextInterface $context): void
    {
      // Seuls les états "Soumise" et "Validée" nécessitent des classifications Dewey
      $etatsNecessitantDewey = [NoticEtat::Forward, NoticEtat::Approved];

      if (in_array($this->etat, $etatsNecessitantDewey)) {
        $hasClassificationDewey = $this->deweyGroups->count() > 0 || $this->deweyPersos->count() > 0;

        if (!$hasClassificationDewey) {
          $labelEtat = $this->etat->getLabel();
          $context->buildViolation('Une notice "{{ etat }}" doit avoir au moins une classification Dewey (groupe ou personnalisé)')
            ->setParameter('{{ etat }}', $labelEtat)
            ->atPath('deweyGroups')
            ->addViolation();
        }
      }
    }
}
