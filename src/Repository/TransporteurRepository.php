<?php

namespace App\Repository;

use App\Entity\Transporteur;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method Transporteur|null find($id, $lockMode = null, $lockVersion = null)
 * @method Transporteur|null findOneBy(array $criteria, array $orderBy = null)
 * @method Transporteur[]    findAll()
 * @method Transporteur[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TransporteurRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, Transporteur::class);
    }

    // /**
    //  * @return Transporteur[] Returns an array of Transporteur objects
    //  */
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
    public function findOneBySomeField($value): ?Transporteur
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
