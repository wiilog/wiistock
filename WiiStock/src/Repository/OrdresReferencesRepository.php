<?php

namespace App\Repository;

use App\Entity\OrdresReferences;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method OrdresReferences|null find($id, $lockMode = null, $lockVersion = null)
 * @method OrdresReferences|null findOneBy(array $criteria, array $orderBy = null)
 * @method OrdresReferences[]    findAll()
 * @method OrdresReferences[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class OrdresReferencesRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, OrdresReferences::class);
    }

//    /**
//     * @return OrdresReferences[] Returns an array of OrdresReferences objects
//     */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('o')
            ->andWhere('o.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('o.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?OrdresReferences
    {
        return $this->createQueryBuilder('o')
            ->andWhere('o.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
