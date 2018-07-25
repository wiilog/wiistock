<?php

namespace App\Repository;

use App\Entity\Transferts;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method Transferts|null find($id, $lockMode = null, $lockVersion = null)
 * @method Transferts|null findOneBy(array $criteria, array $orderBy = null)
 * @method Transferts[]    findAll()
 * @method Transferts[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TransfertsRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, Transferts::class);
    }

//    /**
//     * @return Transferts[] Returns an array of Transferts objects
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
    public function findOneBySomeField($value): ?Transferts
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
