<?php

namespace App\Repository;

use App\Entity\ParamInventory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method ParamInventory|null find($id, $lockMode = null, $lockVersion = null)
 * @method ParamInventory|null findOneBy(array $criteria, array $orderBy = null)
 * @method ParamInventory[]    findAll()
 * @method ParamInventory[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ParamInventoryRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, ParamInventory::class);
    }

    // /**
    //  * @return ParamInventory[] Returns an array of ParamInventory objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('p.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?ParamInventory
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
