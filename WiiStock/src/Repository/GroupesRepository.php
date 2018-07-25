<?php

namespace App\Repository;

use App\Entity\Groupes;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method Groupes|null find($id, $lockMode = null, $lockVersion = null)
 * @method Groupes|null findOneBy(array $criteria, array $orderBy = null)
 * @method Groupes[]    findAll()
 * @method Groupes[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class GroupesRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, Groupes::class);
    }

//    /**
//     * @return Groupes[] Returns an array of Groupes objects
//     */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('g')
            ->andWhere('g.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('g.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?Groupes
    {
        return $this->createQueryBuilder('g')
            ->andWhere('g.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
