<?php

namespace App\Entity;

use App\Repository\CharacterLevelRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CharacterLevelRepository::class)]
#[ORM\Table(name: 'character_levels')]
class CharacterLevel
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: "IDENTITY")]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private ?int $level = null;

    #[ORM\Column(type: "bigint")]
    private ?string $totalExperience = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLevel(): ?int
    {
        return $this->level;
    }

    public function setLevel(int $level): self
    {
        $this->level = $level;
        return $this;
    }

    public function getTotalExperience(): ?string
    {
        return $this->totalExperience;
    }

    public function setTotalExperience(string $totalExperience): self
    {
        $this->totalExperience = $totalExperience;
        return $this;
    }
}
