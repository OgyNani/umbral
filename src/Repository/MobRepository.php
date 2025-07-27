<?php

namespace App\Repository;

use App\Entity\Mob;
use App\Entity\Location;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Mob>
 */
class MobRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Mob::class);
    }

    /**
     * Find mobs by location
     */
    public function findByLocation(Location $location): array
    {
        return $this->findBy(['location' => $location]);
    }
    
    /**
     * Find mobs by level range
     */
    public function findByLevelRange(int $minLevel, int $maxLevel): array
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.level >= :minLevel')
            ->andWhere('m.level <= :maxLevel')
            ->setParameter('minLevel', $minLevel)
            ->setParameter('maxLevel', $maxLevel)
            ->getQuery()
            ->getResult();
    }
    
    /**
     * Find mobs by location and level range
     */
    public function findByLocationAndLevelRange(Location $location, int $minLevel, int $maxLevel): array
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.location = :location')
            ->andWhere('m.level >= :minLevel')
            ->andWhere('m.level <= :maxLevel')
            ->setParameter('location', $location)
            ->setParameter('minLevel', $minLevel)
            ->setParameter('maxLevel', $maxLevel)
            ->getQuery()
            ->getResult();
    }
    
    /**
     * Save mob entity
     */
    public function save(Mob $mob, bool $flush = true): void
    {
        $this->getEntityManager()->persist($mob);
        
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
    
    /**
     * Remove mob entity
     */
    public function remove(Mob $mob, bool $flush = true): void
    {
        $this->getEntityManager()->remove($mob);
        
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
