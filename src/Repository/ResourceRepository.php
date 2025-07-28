<?php

namespace App\Repository;

use App\Entity\Resource;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Resource>
 *
 * @method Resource|null find($id, $lockMode = null, $lockVersion = null)
 * @method Resource|null findOneBy(array $criteria, array $orderBy = null)
 * @method Resource[]    findAll()
 * @method Resource[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ResourceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Resource::class);
    }

    public function save(Resource $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Resource $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Находит ресурсы по категории
     */
    public function findByCategory(string $category): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.category = :category')
            ->setParameter('category', $category)
            ->orderBy('r.rarity', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Находит ресурсы по редкости
     */
    public function findByRarity(string $rarity): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.rarity = :rarity')
            ->setParameter('rarity', $rarity)
            ->orderBy('r.category', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Находит ресурсы по категории и редкости
     */
    public function findByCategoryAndRarity(string $category, string $rarity): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.category = :category')
            ->andWhere('r.rarity = :rarity')
            ->setParameter('category', $category)
            ->setParameter('rarity', $rarity)
            ->getQuery()
            ->getResult()
        ;
    }
}
