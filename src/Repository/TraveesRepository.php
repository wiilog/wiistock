<?php

namespace App\Repository;

use App\Entity\Travees;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method Travees|null find($id, $lockMode = null, $lockVersion = null)
 * @method Travees|null findOneBy(array $criteria, array $orderBy = null)
 * @method Travees[]    findAll()
 * @method Travees[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TraveesRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, Travees::class);
    }

//    /**
//     * @return Travees[] Returns an array of Travees objects
//     */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('t.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?Travees
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
