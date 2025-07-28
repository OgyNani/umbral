<?php

namespace App\Repository;

use App\Entity\User;
use App\Entity\UserGatheringLevel;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UserGatheringLevel>
 */
class UserGatheringLevelRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserGatheringLevel::class);
    }

    public function save(UserGatheringLevel $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(UserGatheringLevel $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Получить или создать запись уровней добычи для пользователя
     */
    public function getOrCreateForUser(User $user): UserGatheringLevel
    {
        $gatheringLevel = $this->findOneBy(['user' => $user]);
        
        if (!$gatheringLevel) {
            $gatheringLevel = new UserGatheringLevel();
            $gatheringLevel->setUser($user);
            $this->save($gatheringLevel, true);
        }
        
        return $gatheringLevel;
    }
    
    /**
     * Найти пользователей с высоким уровнем указанного навыка
     */
    public function findTopUsersBySkill(string $skillName, int $limit = 10): array
    {
        $validSkills = ['alchemy', 'hunting', 'mines', 'fishing', 'farm'];
        
        if (!in_array($skillName, $validSkills)) {
            throw new \InvalidArgumentException('Invalid skill name');
        }
        
        $fieldName = $skillName . 'Lvl';
        
        return $this->createQueryBuilder('u')
            ->orderBy('u.' . $fieldName, 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
