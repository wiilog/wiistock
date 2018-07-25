<?php

namespace App\Repository;

use App\Entity\DemandesLivraisons;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method DemandesLivraisons|null find($id, $lockMode = null, $lockVersion = null)
 * @method DemandesLivraisons|null findOneBy(array $criteria, array $orderBy = null)
 * @method DemandesLivraisons[]    findAll()
 * @method DemandesLivraisons[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class DemandesLivraisonsRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, DemandesLivraisons::class);
    }

//    /**
//     * @return DemandesLivraisons[] Returns an array of DemandesLivraisons objects
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
    public function findOneBySomeField($value): ?DemandesLivraisons
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
