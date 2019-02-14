<?php

namespace App\Repository;

use App\Entity\Racks;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method Racks|null find($id, $lockMode = null, $lockVersion = null)
 * @method Racks|null findOneBy(array $criteria, array $orderBy = null)
 * @method Racks[]    findAll()
 * @method Racks[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class RacksRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, Racks::class);
    }

//    /**
//     * @return Racks[] Returns an array of Racks objects
//     */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('r.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?Racks
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
