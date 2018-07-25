<?php

namespace App\Repository;

use App\Entity\DemandesTransferts;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method DemandesTransferts|null find($id, $lockMode = null, $lockVersion = null)
 * @method DemandesTransferts|null findOneBy(array $criteria, array $orderBy = null)
 * @method DemandesTransferts[]    findAll()
 * @method DemandesTransferts[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class DemandesTransfertsRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, DemandesTransferts::class);
    }

//    /**
//     * @return DemandesTransferts[] Returns an array of DemandesTransferts objects
//     */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('d.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?DemandesTransferts
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
