<?php

namespace App\Repository;

use App\Entity\MissionInv;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method MissionInv|null find($id, $lockMode = null, $lockVersion = null)
 * @method MissionInv|null findOneBy(array $criteria, array $orderBy = null)
 * @method MissionInv[]    findAll()
 * @method MissionInv[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class MissionInvRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, MissionInv::class);
    }

    // /**
    //  * @return MissionInv[] Returns an array of MissionInv objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('m.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?MissionInv
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
