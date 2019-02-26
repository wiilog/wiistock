<?php

namespace App\Repository;

use App\Entity\ValeurChampsLibre;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method ValeurChampsLibre|null find($id, $lockMode = null, $lockVersion = null)
 * @method ValeurChampsLibre|null findOneBy(array $criteria, array $orderBy = null)
 * @method ValeurChampsLibre[]    findAll()
 * @method ValeurChampsLibre[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ValeurChampsLibreRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, ValeurChampsLibre::class);
    }

    // /**
    //  * @return ValeurChampsLibre[] Returns an array of ValeurChampsLibre objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('v')
            ->andWhere('v.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('v.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?ValeurChampsLibre
    {
        return $this->createQueryBuilder('v')
            ->andWhere('v.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
