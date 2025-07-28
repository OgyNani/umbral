<?php

namespace App\Repository;

use App\Entity\GatheringLevel;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<GatheringLevel>
 */
class GatheringLevelRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, GatheringLevel::class);
    }

    /**
     * Получить уровень на основе накопленного опыта
     *
     * @param int $experience Общий накопленный опыт
     * @return int Текущий уровень
     */
    public function getLevelByExperience(int $experience): int
    {
        $level = $this->createQueryBuilder('gl')
            ->select('gl.level')
            ->where('gl.totalExperience <= :experience')
            ->setParameter('experience', (string)$experience)
            ->orderBy('gl.level', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
        
        return $level ? $level['level'] : 1;
    }
    
    /**
     * Получить требуемый опыт для достижения следующего уровня
     *
     * @param int $currentLevel Текущий уровень
     * @return int|null Требуемый опыт для следующего уровня или null, если текущий уровень максимальный
     */
    public function getExperienceForNextLevel(int $currentLevel): ?int
    {
        if ($currentLevel >= 150) {
            return null; // Максимальный уровень достигнут
        }
        
        $nextLevel = $currentLevel + 1;
        $result = $this->createQueryBuilder('gl')
            ->select('gl.totalExperience')
            ->where('gl.level = :nextLevel')
            ->setParameter('nextLevel', $nextLevel)
            ->getQuery()
            ->getOneOrNullResult();
        
        return $result ? (int)$result['totalExperience'] : null;
    }
    
    /**
     * Сохранить сущность в базе данных
     */
    public function save(GatheringLevel $gatheringLevel, bool $flush = false): void
    {
        $this->getEntityManager()->persist($gatheringLevel);
        
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
