<?php

namespace App\Entity\Dto;

use App\Entity\{Auteur, Etablissement, Niveau, Notice};

readonly class IndexingNotice
{
    public function __construct(
        private ?string $uuid = null,
        private ?string $title = null,
        private ?string $description = null,
        private ?string $formEvalUrl = null,
        private ?string $entrepot_logo = null,
        private ?string $vignette = null,
        private ?string $dewey = null,
        private ?string $specialite = null,
        private ?string $correspondant = null,
        private ?string $dureeApprentissage = null,
        private ?string $objectifsPedagogiques = null,
        private ?string $propositionUtilisation = null,
        private ?string $motsCles = null,
        private ?string $niveaux = null,
        private ?string $typesDocumentaires = null,
        private ?string $typesPedagogiques = null,
        private ?string $contributions = null,
        private ?string $etablissementPorteur = null,
        private ?string $languesUtilisateur = null,
        private ?string $languesPessource = null,
        private ?string $datePublication = null,
        private ?string $dateModification = null,
        private ?bool $ressourcePayante = null,
        private ?bool $proprieteIntellectuelle = null,
        private ?bool $exportOai = null,
        private bool $externalResource = true)
    {}

    static function create(Notice $notice): IndexingNotice
    {
        return new self(
            $notice->getUuid(),
            $notice->getTitre(),
            $notice->getDescription(),
            $notice->getFormEvalUrl(),
            "none",
            $notice->getVignette(),
            $notice->getCodewey(),
            $notice->getSpecialite(),
            $notice->getCreateur(),
            $notice->getDureAppr(),
            $notice->getObjectif(),
            implode(", ", (array)$notice->getPropUser()),
            implode(", ", $notice->getTags()->toArray()),
            array_reduce($notice->getNiveaux()->toArray(),fn(string $tmp, Niveau $etab): string => $tmp.sprintf("%s, ",$etab->getNom()),""),
            implode(", ", $notice->getDocTypes()->toArray()),
            implode(", ", $notice->getPedTypes()->toArray()),
            array_reduce($notice->getAuteurs()->toArray(),fn(string $tmp, Auteur $etab): string => $tmp.sprintf("{nom=%s %s, email=%s}, ",$etab->getPrenom(),$etab->getNom(),$etab->getEmail()),""),
            array_reduce($notice->getPorteurs()->toArray(),fn(string $tmp, Etablissement $etab): string => $tmp.sprintf("%s, ",$etab->getNom()),""),
            implode(", ", (array)$notice->getUserLang()),
            implode(", ", (array)$notice->getRessLang()),
            $notice->getPublieLe()?->format('Y-m-d H:i:s'),
            $notice->getEditeLe()?->format('Y-m-d H:i:s'),
            $notice->isRessPayant(), $notice->isProprIntel(),
            $notice->isExportOai(), false,
        );
    }

    public function getUuid(): ?string
    {
        return $this->uuid;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getFormEvalUrl(): ?string
    {
        return $this->formEvalUrl;
    }

    public function getEntrepotLogo(): ?string
    {
        return $this->entrepot_logo;
    }

    public function getVignette(): ?string
    {
        return $this->vignette;
    }

    public function getDewey(): ?string
    {
        return $this->dewey;
    }

    public function getSpecialite(): ?string
    {
        return $this->specialite;
    }

    public function getCorrespondant(): ?string
    {
        return $this->correspondant;
    }

    public function getDureeApprentissage(): ?string
    {
        return $this->dureeApprentissage;
    }

    public function getObjectifsPedagogiques(): ?string
    {
        return $this->objectifsPedagogiques;
    }

    public function getPropositionUtilisation(): ?string
    {
        return $this->propositionUtilisation;
    }

    public function getMotsCles(): ?string
    {
        return $this->motsCles;
    }

    public function getNiveaux(): ?string
    {
        return $this->niveaux;
    }

    public function getTypesDocumentaires(): ?string
    {
        return $this->typesDocumentaires;
    }

    public function getTypesPedagogiques(): ?string
    {
        return $this->typesPedagogiques;
    }

    public function getContributions(): ?string
    {
        return $this->contributions;
    }

    public function getEtablissementPorteur(): ?string
    {
        return $this->etablissementPorteur;
    }

    public function getLanguesUtilisateur(): ?string
    {
        return $this->languesUtilisateur;
    }

    public function getLanguesPessource(): ?string
    {
        return $this->languesPessource;
    }

    public function getDatePublication(): ?string
    {
        return $this->datePublication;
    }

    public function getDateModification(): ?string
    {
        return $this->dateModification;
    }

    public function getRessourcePayante(): ?bool
    {
        return $this->ressourcePayante;
    }

    public function getProprieteIntellectuelle(): ?bool
    {
        return $this->proprieteIntellectuelle;
    }

    public function getExportOai(): ?bool
    {
        return $this->exportOai;
    }

    public function isExternalResource(): bool
    {
        return $this->externalResource;
    }

    public function __toString(): string
    {
        $field = "";
        foreach ($this as $key => $value) {
            if ($key==='description') $value = strip_tags($value);
            $field .= sprintf("<field name='%s'>%s</field>", $key, $value===false ? 0 : $value);
        }
        return $field;
    }


}