<?php

namespace App\Repository;

use App\Entity\CategoriesVehicules;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method CategoriesVehicules|null find($id, $lockMode = null, $lockVersion = null)
 * @method CategoriesVehicules|null findOneBy(array $criteria, array $orderBy = null)
 * @method CategoriesVehicules[]    findAll()
 * @method CategoriesVehicules[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CategoriesVehiculesRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, CategoriesVehicules::class);
    }

//    /**
//     * @return CategoriesVehicules[] Returns an array of CategoriesVehicules objects
//     */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('c.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?CategoriesVehicules
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
