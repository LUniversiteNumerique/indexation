<?php

namespace App\Entity;

use DateTimeInterface;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

trait Timestamps
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?DateTimeInterface $creeLe;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?DateTimeInterface $editeLe = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCreeLe(): ?DateTimeInterface
    {
        return $this->creeLe;
    }

    public function setCreeLe(?DateTimeInterface $creeLe): self
    {
        $this->creeLe = $creeLe;

        return $this;
    }

    public function getEditeLe(): ?DateTimeInterface
    {
        return $this->editeLe;
    }

    public function setEditeLe(?DateTimeInterface $editeLe): self
    {
        $this->editeLe = $editeLe;

        return $this;
    }
}