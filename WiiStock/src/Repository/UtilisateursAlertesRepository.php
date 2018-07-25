<?php

namespace App\Repository;

use App\Entity\UtilisateursAlertes;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method UtilisateursAlertes|null find($id, $lockMode = null, $lockVersion = null)
 * @method UtilisateursAlertes|null findOneBy(array $criteria, array $orderBy = null)
 * @method UtilisateursAlertes[]    findAll()
 * @method UtilisateursAlertes[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class UtilisateursAlertesRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, UtilisateursAlertes::class);
    }

//    /**
//     * @return UtilisateursAlertes[] Returns an array of UtilisateursAlertes objects
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
    public function findOneBySomeField($value): ?UtilisateursAlertes
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
