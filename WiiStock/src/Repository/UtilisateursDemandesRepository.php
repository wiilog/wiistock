<?php

namespace App\Repository;

use App\Entity\UtilisateursDemandes;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method UtilisateursDemandes|null find($id, $lockMode = null, $lockVersion = null)
 * @method UtilisateursDemandes|null findOneBy(array $criteria, array $orderBy = null)
 * @method UtilisateursDemandes[]    findAll()
 * @method UtilisateursDemandes[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class UtilisateursDemandesRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, UtilisateursDemandes::class);
    }

//    /**
//     * @return UtilisateursDemandes[] Returns an array of UtilisateursDemandes objects
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
    public function findOneBySomeField($value): ?UtilisateursDemandes
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
