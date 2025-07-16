<?php

namespace App\Entity;

use App\Repository\MobAbilityRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MobAbilityRepository::class)]
#[ORM\Table(name: 'mob_abilities')]
class MobAbility
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: "IDENTITY")]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(type: 'text')]
    private ?string $description = null;

    #[ORM\Column(length: 50)]
    private ?string $type = null;

    #[ORM\Column(type: 'json')]
    private array $effect = [];

    #[ORM\Column(length: 50)]
    private ?string $trigger_effect = null;

    #[ORM\Column]
    private ?int $cooldown = null;

    #[ORM\ManyToOne(inversedBy: 'mobAbilities')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Mob $mob = null;

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

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;
        return $this;
    }

    public function getEffect(): array
    {
        return $this->effect;
    }

    public function setEffect(array $effect): static
    {
        $this->effect = $effect;
        return $this;
    }

    public function getTriggerEffect(): ?string
    {
        return $this->trigger_effect;
    }

    public function setTriggerEffect(string $trigger_effect): static
    {
        $this->trigger_effect = $trigger_effect;
        return $this;
    }

    public function getCooldown(): ?int
    {
        return $this->cooldown;
    }

    public function setCooldown(int $cooldown): static
    {
        $this->cooldown = $cooldown;
        return $this;
    }

    public function getMob(): ?Mob
    {
        return $this->mob;
    }

    public function setMob(?Mob $mob): static
    {
        $this->mob = $mob;
        return $this;
    }
}
