<?php

namespace App\Repository;

use App\Entity\DemandesReferences;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method DemandesReferences|null find($id, $lockMode = null, $lockVersion = null)
 * @method DemandesReferences|null findOneBy(array $criteria, array $orderBy = null)
 * @method DemandesReferences[]    findAll()
 * @method DemandesReferences[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class DemandesReferencesRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, DemandesReferences::class);
    }

//    /**
//     * @return DemandesReferences[] Returns an array of DemandesReferences objects
//     */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('d.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?DemandesReferences
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
