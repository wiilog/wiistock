<?php

namespace App\Repository;

use App\Entity\ZoneQuai;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method ZoneQuai|null find($id, $lockMode = null, $lockVersion = null)
 * @method ZoneQuai|null findOneBy(array $criteria, array $orderBy = null)
 * @method ZoneQuai[]    findAll()
 * @method ZoneQuai[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ZoneQuaiRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, ZoneQuai::class);
    }

//    /**
//     * @return ZoneQuai[] Returns an array of ZoneQuai objects
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
    public function findOneBySomeField($value): ?ZoneQuai
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
