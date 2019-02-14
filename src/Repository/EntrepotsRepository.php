<?php

namespace App\Repository;

use App\Entity\Entrepots;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method Entrepots|null find($id, $lockMode = null, $lockVersion = null)
 * @method Entrepots|null findOneBy(array $criteria, array $orderBy = null)
 * @method Entrepots[]    findAll()
 * @method Entrepots[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class EntrepotsRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, Entrepots::class);
    }

//    /**
//     * @return Entrepots[] Returns an array of Entrepots objects
//     */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('e.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?Entrepots
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
