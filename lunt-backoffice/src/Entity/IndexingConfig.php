<?php

namespace App\Entity;

use App\Repository\IndexingConfigRepository;
use Doctrine\DBAL\Types\Types;
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

    #[ORM\Column]
    private ?\DateTime $scheduleAt;

    #[ORM\Column(type: Types::SMALLINT)]
    private ?int $filesIn = null;

    #[ORM\Column(type: Types::SMALLINT)]
    private ?int $filesOut = null;

    #[ORM\Column(type: Types::INTEGER)]
    private ?int $inDuration = null;

    public function __construct(?bool $mode = false, ?bool $type = false, ?int $fIn = null, ?int $fOut = null, ?int $inDur = null)
    {
        $this->fullMode = $mode;
        $this->indexType = $type; //0=intern, 1=extern
        $this->filesIn = $fIn;
        $this->filesOut = $fOut;
        $this->inDuration = $inDur;
        $this->scheduleAt = new \DateTime();
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

    public function setScheduleAt(\DateTime $scheduleAt): static
    {
        $this->scheduleAt = $scheduleAt;

        return $this;
    }

    public function getFilesIn(): ?int
    {
        return $this->filesIn;
    }

    public function setFilesIn(int $filesIn): static
    {
        $this->filesIn = $filesIn;

        return $this;
    }

    public function getFilesOut(): ?int
    {
        return $this->filesOut;
    }

    public function setFilesOut(int $filesOut): static
    {
        $this->filesOut = $filesOut;

        return $this;
    }

    public function getInDuration(): ?int
    {
        return $this->inDuration;
    }

    public function setInDuration(?int $inDuration): static
    {
        $this->inDuration = $inDuration;

        return $this;
    }
}
