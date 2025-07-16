<?php

namespace App\Entity;

use App\Repository\MobRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MobRepository::class)]
#[ORM\Table(name: 'mobs')]
class Mob
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: "IDENTITY")]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: 'json')]
    private array $stats = [];

    #[ORM\Column]
    private ?int $exp_reward = null;

    #[ORM\Column]
    private ?int $gold_reward = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Location $location = null;

    #[ORM\Column]
    private ?int $level = null;

    #[ORM\Column(type: 'json')]
    private array $abilities = [];

    #[ORM\Column(type: 'json')]
    private array $loot = [];

    #[ORM\OneToMany(mappedBy: 'mob', targetEntity: MobAbility::class)]
    private Collection $mobAbilities;

    public function __construct()
    {
        $this->mobAbilities = new ArrayCollection();
    }

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

    public function setDescription(?string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function getStats(): array
    {
        return $this->stats;
    }

    public function setStats(array $stats): static
    {
        $this->stats = $stats;
        return $this;
    }

    public function getExpReward(): ?int
    {
        return $this->exp_reward;
    }

    public function setExpReward(int $exp_reward): static
    {
        $this->exp_reward = $exp_reward;
        return $this;
    }

    public function getGoldReward(): ?int
    {
        return $this->gold_reward;
    }

    public function setGoldReward(int $gold_reward): static
    {
        $this->gold_reward = $gold_reward;
        return $this;
    }

    public function getLocation(): ?Location
    {
        return $this->location;
    }

    public function setLocation(?Location $location): static
    {
        $this->location = $location;
        return $this;
    }

    public function getLevel(): ?int
    {
        return $this->level;
    }

    public function setLevel(int $level): static
    {
        $this->level = $level;
        return $this;
    }

    public function getAbilities(): array
    {
        return $this->abilities;
    }

    public function setAbilities(array $abilities): static
    {
        $this->abilities = $abilities;
        return $this;
    }

    public function getLoot(): array
    {
        return $this->loot;
    }

    public function setLoot(array $loot): static
    {
        $this->loot = $loot;
        return $this;
    }

    public function getMobAbilities(): Collection
    {
        return $this->mobAbilities;
    }

    public function addMobAbility(MobAbility $mobAbility): static
    {
        if (!$this->mobAbilities->contains($mobAbility)) {
            $this->mobAbilities->add($mobAbility);
            $mobAbility->setMob($this);
        }
        return $this;
    }

    public function removeMobAbility(MobAbility $mobAbility): static
    {
        if ($this->mobAbilities->removeElement($mobAbility)) {
            if ($mobAbility->getMob() === $this) {
                $mobAbility->setMob(null);
            }
        }
        return $this;
    }
}
