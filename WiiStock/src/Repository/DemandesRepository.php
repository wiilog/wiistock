<?php

namespace App\Repository;

use App\Entity\Demandes;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method Demandes|null find($id, $lockMode = null, $lockVersion = null)
 * @method Demandes|null findOneBy(array $criteria, array $orderBy = null)
 * @method Demandes[]    findAll()
 * @method Demandes[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class DemandesRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, Demandes::class);
    }

//    /**
//     * @return Demandes[] Returns an array of Demandes objects
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
    public function findOneBySomeField($value): ?Demandes
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
