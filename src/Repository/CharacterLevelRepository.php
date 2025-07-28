<?php

namespace App\Repository;

use App\Entity\CharacterLevel;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CharacterLevel>
 */
class CharacterLevelRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CharacterLevel::class);
    }

    public function save(CharacterLevel $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(CharacterLevel $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
    
    /**
     * Find a level entry by level number
     */
    public function findByLevel(int $level): ?CharacterLevel
    {
        return $this->findOneBy(['level' => $level]);
    }
    
    /**
     * Find level by total experience
     * Returns the highest level that requires less or equal experience than given
     */
    public function findLevelByExperience(string $experience): ?CharacterLevel
    {
        $queryBuilder = $this->createQueryBuilder('cl')
            ->where('cl.totalExperience <= :experience')
            ->setParameter('experience', $experience)
            ->orderBy('cl.level', 'DESC')
            ->setMaxResults(1);
            
        return $queryBuilder->getQuery()->getOneOrNullResult();
    }
}
