<?php

namespace App\Repository;

use App\Entity\InventairesReferences;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method InventairesReferences|null find($id, $lockMode = null, $lockVersion = null)
 * @method InventairesReferences|null findOneBy(array $criteria, array $orderBy = null)
 * @method InventairesReferences[]    findAll()
 * @method InventairesReferences[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class InventairesReferencesRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, InventairesReferences::class);
    }

//    /**
//     * @return InventairesReferences[] Returns an array of InventairesReferences objects
//     */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('i')
            ->andWhere('i.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('i.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?InventairesReferences
    {
        return $this->createQueryBuilder('i')
            ->andWhere('i.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
