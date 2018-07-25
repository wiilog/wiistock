<?php

namespace App\Repository;

use App\Entity\UtilisateursOrdres;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method UtilisateursOrdres|null find($id, $lockMode = null, $lockVersion = null)
 * @method UtilisateursOrdres|null findOneBy(array $criteria, array $orderBy = null)
 * @method UtilisateursOrdres[]    findAll()
 * @method UtilisateursOrdres[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class UtilisateursOrdresRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, UtilisateursOrdres::class);
    }

//    /**
//     * @return UtilisateursOrdres[] Returns an array of UtilisateursOrdres objects
//     */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('u.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?UtilisateursOrdres
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
