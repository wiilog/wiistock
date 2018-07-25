<?php

namespace App\Repository;

use App\Entity\Emplacements;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method Emplacements|null find($id, $lockMode = null, $lockVersion = null)
 * @method Emplacements|null findOneBy(array $criteria, array $orderBy = null)
 * @method Emplacements[]    findAll()
 * @method Emplacements[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class EmplacementsRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, Emplacements::class);
    }

//    /**
//     * @return Emplacements[] Returns an array of Emplacements objects
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
    public function findOneBySomeField($value): ?Emplacements
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
