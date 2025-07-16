<?php

namespace App\Entity;

use App\Repository\CharacterClassRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CharacterClassRepository::class)]
#[ORM\Table(name: 'classes')]
class CharacterClass
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: "IDENTITY")]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(type: 'json')]
    private array $base_stats = [];

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;
        return $this;
    }

    public function getBaseStats(): array
    {
        return $this->base_stats;
    }

    public function setBaseStats(array $base_stats): static
    {
        $this->base_stats = $base_stats;
        return $this;
    }
}
