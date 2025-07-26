<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'users')]
class User
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: "IDENTITY")]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private ?int $telegram_id = null;

    #[ORM\Column(nullable: true)]
    private ?int $current_character_id = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $created_at = null;

    #[ORM\OneToMany(mappedBy: 'user', targetEntity: Character::class)]
    private Collection $characters;

    public function __construct()
    {
        $this->characters = new ArrayCollection();
        $this->created_at = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTelegramId(): ?int
    {
        return $this->telegram_id;
    }

    public function setTelegramId(int $telegram_id): static
    {
        $this->telegram_id = $telegram_id;
        return $this;
    }

    public function getCurrentCharacterId(): ?int
    {
        return $this->current_character_id;
    }

    public function setCurrentCharacterId(?int $current_character_id): static
    {
        $this->current_character_id = $current_character_id;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->created_at;
    }

    public function getCharacters(): Collection
    {
        return $this->characters;
    }

    public function addCharacter(Character $character): static
    {
        if (!$this->characters->contains($character)) {
            $this->characters->add($character);
            $character->setUser($this);
        }
        return $this;
    }

    public function removeCharacter(Character $character): static
    {
        if ($this->characters->removeElement($character)) {
            if ($character->getUser() === $this) {
                $character->setUser(null);
            }
        }
        return $this;
    }
    
    public function getCharacter(): ?Character
    {
        if ($this->current_character_id === null) {
            return $this->characters->isEmpty() ? null : $this->characters->first();
        }
        
        foreach ($this->characters as $character) {
            if ($character->getId() === $this->current_character_id) {
                return $character;
            }
        }
        
        return null;
    }
}
