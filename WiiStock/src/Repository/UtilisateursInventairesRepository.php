<?php

namespace App\Repository;

use App\Entity\UtilisateursInventaires;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method UtilisateursInventaires|null find($id, $lockMode = null, $lockVersion = null)
 * @method UtilisateursInventaires|null findOneBy(array $criteria, array $orderBy = null)
 * @method UtilisateursInventaires[]    findAll()
 * @method UtilisateursInventaires[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class UtilisateursInventairesRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, UtilisateursInventaires::class);
    }

//    /**
//     * @return UtilisateursInventaires[] Returns an array of UtilisateursInventaires objects
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
    public function findOneBySomeField($value): ?UtilisateursInventaires
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
