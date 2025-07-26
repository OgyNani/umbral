<?php

namespace App\Repository;

use App\Entity\Character;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Character>
 */
class CharacterRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Character::class);
    }

    public function save(Character $character, bool $flush = true): void
    {
        $this->getEntityManager()->persist($character);
        
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
    
    public function findByName(string $name): ?Character
    {
        return $this->findOneBy(['name' => $name]);
    }
}
