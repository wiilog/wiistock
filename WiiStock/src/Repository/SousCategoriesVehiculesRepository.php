<?php

namespace App\Repository;

use App\Entity\SousCategoriesVehicules;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method SousCategoriesVehicules|null find($id, $lockMode = null, $lockVersion = null)
 * @method SousCategoriesVehicules|null findOneBy(array $criteria, array $orderBy = null)
 * @method SousCategoriesVehicules[]    findAll()
 * @method SousCategoriesVehicules[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class SousCategoriesVehiculesRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, SousCategoriesVehicules::class);
    }

//    /**
//     * @return SousCategoriesVehicules[] Returns an array of SousCategoriesVehicules objects
//     */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('s.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?SousCategoriesVehicules
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
