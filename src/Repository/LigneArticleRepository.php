<?php

namespace App\Repository;

use App\Entity\LigneArticle;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method LigneArticle|null find($id, $lockMode = null, $lockVersion = null)
 * @method LigneArticle|null findOneBy(array $criteria, array $orderBy = null)
 * @method LigneArticle[]    findAll()
 * @method LigneArticle[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class LigneArticleRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, LigneArticle::class);
    }

    // /**
    //  * @return LigneArticle[] Returns an array of LigneArticle objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('l')
            ->andWhere('l.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('l.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?LigneArticle
    {
        return $this->createQueryBuilder('l')
            ->andWhere('l.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
