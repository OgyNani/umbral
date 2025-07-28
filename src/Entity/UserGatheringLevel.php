<?php

namespace App\Entity;

use App\Repository\UserGatheringLevelRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UserGatheringLevelRepository::class)]
#[ORM\Table(name: 'user_gathering_level')]
class UserGatheringLevel
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false)]
    private ?User $user = null;

    #[ORM\Column(options: ["default" => 0])]
    private ?int $alchemyLvl = 0;

    #[ORM\Column(options: ["default" => 0])]
    private ?int $huntingLvl = 0;

    #[ORM\Column(options: ["default" => 0])]
    private ?int $minesLvl = 0;

    #[ORM\Column(options: ["default" => 0])]
    private ?int $fishingLvl = 0;

    #[ORM\Column(options: ["default" => 0])]
    private ?int $farmLvl = 0;

    #[ORM\Column(options: ["default" => 0])]
    private ?int $alchemyExp = 0;

    #[ORM\Column(options: ["default" => 0])]
    private ?int $huntingExp = 0;

    #[ORM\Column(options: ["default" => 0])]
    private ?int $minesExp = 0;

    #[ORM\Column(options: ["default" => 0])]
    private ?int $fishingExp = 0;

    #[ORM\Column(options: ["default" => 0])]
    private ?int $farmExp = 0;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): self
    {
        $this->user = $user;
        return $this;
    }

    public function getAlchemyLvl(): ?int
    {
        return $this->alchemyLvl;
    }

    public function setAlchemyLvl(int $alchemyLvl): self
    {
        $this->alchemyLvl = $alchemyLvl;
        return $this;
    }

    public function getHuntingLvl(): ?int
    {
        return $this->huntingLvl;
    }

    public function setHuntingLvl(int $huntingLvl): self
    {
        $this->huntingLvl = $huntingLvl;
        return $this;
    }

    public function getMinesLvl(): ?int
    {
        return $this->minesLvl;
    }

    public function setMinesLvl(int $minesLvl): self
    {
        $this->minesLvl = $minesLvl;
        return $this;
    }

    public function getFishingLvl(): ?int
    {
        return $this->fishingLvl;
    }

    public function setFishingLvl(int $fishingLvl): self
    {
        $this->fishingLvl = $fishingLvl;
        return $this;
    }

    public function getFarmLvl(): ?int
    {
        return $this->farmLvl;
    }

    public function setFarmLvl(int $farmLvl): self
    {
        $this->farmLvl = $farmLvl;
        return $this;
    }

    public function getAlchemyExp(): ?int
    {
        return $this->alchemyExp;
    }

    public function setAlchemyExp(int $alchemyExp): self
    {
        $this->alchemyExp = $alchemyExp;
        return $this;
    }

    public function getHuntingExp(): ?int
    {
        return $this->huntingExp;
    }

    public function setHuntingExp(int $huntingExp): self
    {
        $this->huntingExp = $huntingExp;
        return $this;
    }

    public function getMinesExp(): ?int
    {
        return $this->minesExp;
    }

    public function setMinesExp(int $minesExp): self
    {
        $this->minesExp = $minesExp;
        return $this;
    }

    public function getFishingExp(): ?int
    {
        return $this->fishingExp;
    }

    public function setFishingExp(int $fishingExp): self
    {
        $this->fishingExp = $fishingExp;
        return $this;
    }

    public function getFarmExp(): ?int
    {
        return $this->farmExp;
    }

    public function setFarmExp(int $farmExp): self
    {
        $this->farmExp = $farmExp;
        return $this;
    }
}
