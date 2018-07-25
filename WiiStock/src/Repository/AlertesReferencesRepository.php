<?php

namespace App\Repository;

use App\Entity\AlertesReferences;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method AlertesReferences|null find($id, $lockMode = null, $lockVersion = null)
 * @method AlertesReferences|null findOneBy(array $criteria, array $orderBy = null)
 * @method AlertesReferences[]    findAll()
 * @method AlertesReferences[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class AlertesReferencesRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, AlertesReferences::class);
    }

//    /**
//     * @return AlertesReferences[] Returns an array of AlertesReferences objects
//     */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('a.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?AlertesReferences
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
