<?php

namespace App\Repository;

use App\Entity\Urgence;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method Urgence|null find($id, $lockMode = null, $lockVersion = null)
 * @method Urgence|null findOneBy(array $criteria, array $orderBy = null)
 * @method Urgence[]    findAll()
 * @method Urgence[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class UrgenceRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, Urgence::class);
    }

    // /**
    //  * @return Urgence[] Returns an array of Urgence objects
    //  */
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
    public function findOneBySomeField($value): ?Urgence
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
