<?php

namespace App\Repository;

use App\Entity\Transfert;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method Transfert|null find($id, $lockMode = null, $lockVersion = null)
 * @method Transfert|null findOneBy(array $criteria, array $orderBy = null)
 * @method Transfert[]    findAll()
 * @method Transfert[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TransfertRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, Transfert::class);
    }

//    /**
//     * @return Transfert[] Returns an array of Transfert objects
//     */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('t.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?Transfert
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
