<?php

namespace App\Entity;

use Symfony\Component\Validator\Constraints as Assert;
use App\Repository\IndexingConfigRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: IndexingConfigRepository::class)]
class IndexingConfig
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private ?bool $fullMode, $indexType;

    #[ORM\Column(nullable: true)]
    private ?\DateTime $scheduleAt=null;

    #[ORM\Column, Assert\NotNull]
    private ?int $batchSize = 10;

    #[ORM\Embedded(Frequence::class, "every_"),
        Assert\NotNull]
    private ?Frequence $frequency;

    #[ORM\ManyToOne, Assert\NotNull,
        Assert\Type(Univerique::class)]
    private ?Univerique $indexCore = null;

    public function __construct()
    {
        $this->frequency = new Frequence();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function isIndexType(): ?bool
    {
        return $this->indexType;
    }

    public function setIndexType(bool $type): static
    {
        $this->indexType = $type;

        return $this;
    }

    public function isFullMode(): ?bool
    {
        return $this->fullMode;
    }

    public function setFullMode(bool $mode): static
    {
        $this->fullMode = $mode;

        return $this;
    }

    public function getScheduleAt(): ?\DateTime
    {
        return $this->scheduleAt;
    }

    public function setScheduleAt(?\DateTime $scheduleAt): static
    {
        $this->scheduleAt = $scheduleAt;

        return $this;
    }

    public function getBatchSize(): ?int
    {
        return $this->batchSize;
    }

    public function setBatchSize(int $batchSize): static
    {
        $this->batchSize = $batchSize;

        return $this;
    }

    public function getFrequency(): ?Frequence
    {
        return $this->frequency;
    }

    public function setFrequency(?Frequence $frequency): static
    {
        $this->frequency = $frequency;

        return $this;
    }

    public function getIndexCore(): ?Univerique
    {
        return $this->indexCore;
    }

    public function setIndexCore(?Univerique $core): static
    {
        $this->indexCore = $core;

        return $this;
    }
}
