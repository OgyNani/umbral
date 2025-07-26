<?php

namespace App\Entity;

use App\Repository\LocationRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: LocationRepository::class)]
#[ORM\Table(name: 'locations')]
class Location
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: "IDENTITY")]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(length: 50)]
    private ?string $type = null;
    
    /**
     * Массив идентификаторов локаций, в которые можно перейти из текущей
     */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $connections = [];

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

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;
        return $this;
    }
    
    /**
     * Получить список доступных локаций для перехода
     */
    public function getConnections(): array
    {
        return $this->connections ?? [];
    }
    
    /**
     * Установить список доступных локаций для перехода
     */
    public function setConnections(array $connections): static
    {
        $this->connections = $connections;
        return $this;
    }
    
    /**
     * Добавить доступную локацию для перехода
     */
    public function addConnection(int $locationId): static
    {
        if (!in_array($locationId, $this->connections)) {
            $this->connections[] = $locationId;
        }
        return $this;
    }
    
    /**
     * Удалить доступную локацию для перехода
     */
    public function removeConnection(int $locationId): static
    {
        $key = array_search($locationId, $this->connections);
        if ($key !== false) {
            unset($this->connections[$key]);
            $this->connections = array_values($this->connections); // Переиндексируем массив
        }
        return $this;
    }
    
    /**
     * Проверить, доступна ли локация для перехода
     */
    public function isConnectionAvailable(int $locationId): bool
    {
        return in_array($locationId, $this->connections);
    }
}
