<?php

namespace App\Repository;

use App\Entity\CharacterClass;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CharacterClass>
 */
class CharacterClassRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CharacterClass::class);
    }

    public function save(CharacterClass $characterClass, bool $flush = true): void
    {
        $this->getEntityManager()->persist($characterClass);
        
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
