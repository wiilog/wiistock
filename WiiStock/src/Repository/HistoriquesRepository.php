<?php

namespace App\Repository;

use App\Entity\Historiques;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method Historiques|null find($id, $lockMode = null, $lockVersion = null)
 * @method Historiques|null findOneBy(array $criteria, array $orderBy = null)
 * @method Historiques[]    findAll()
 * @method Historiques[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class HistoriquesRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, Historiques::class);
    }

//    /**
//     * @return Historiques[] Returns an array of Historiques objects
//     */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('h')
            ->andWhere('h.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('h.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?Historiques
    {
        return $this->createQueryBuilder('h')
            ->andWhere('h.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
