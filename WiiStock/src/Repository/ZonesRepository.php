<?php

namespace App\Repository;

use App\Entity\Zones;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method Zones|null find($id, $lockMode = null, $lockVersion = null)
 * @method Zones|null findOneBy(array $criteria, array $orderBy = null)
 * @method Zones[]    findAll()
 * @method Zones[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ZonesRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, Zones::class);
    }

//    /**
//     * @return Zones[] Returns an array of Zones objects
//     */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('z')
            ->andWhere('z.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('z.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?Zones
    {
        return $this->createQueryBuilder('z')
            ->andWhere('z.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
