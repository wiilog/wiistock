<?php

namespace App\Repository;

use App\Entity\ArticlesHistoriques;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method ArticlesHistoriques|null find($id, $lockMode = null, $lockVersion = null)
 * @method ArticlesHistoriques|null findOneBy(array $criteria, array $orderBy = null)
 * @method ArticlesHistoriques[]    findAll()
 * @method ArticlesHistoriques[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ArticlesHistoriquesRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, ArticlesHistoriques::class);
    }

//    /**
//     * @return ArticlesHistoriques[] Returns an array of ArticlesHistoriques objects
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
    public function findOneBySomeField($value): ?ArticlesHistoriques
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
