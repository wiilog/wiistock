<?php

namespace App\Repository;

use App\Entity\DaysWorked;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method DaysWorked|null find($id, $lockMode = null, $lockVersion = null)
 * @method DaysWorked|null findOneBy(array $criteria, array $orderBy = null)
 * @method DaysWorked[]    findAll()
 * @method DaysWorked[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class DaysWorkedRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, DaysWorked::class);
    }

    public function findAllOrdered()
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
        /** @lang DQL */
            "SELECT dp
            FROM App\Entity\DaysWorked dp 
            ORDER BY dp.displayOrder ASC
            "
        );
        return $query->execute();
    }

    /**
     * @param $day
     * @return DaysWorked
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function findByDayAndWorked($day)
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
        /** @lang DQL */
            "SELECT dp
            FROM App\Entity\DaysWorked dp 
            WHERE dp.day LIKE :day AND dp.worked = 1
            "
        )->setParameter('day', $day);
        return $query->getOneOrNullResult();
    }
}
