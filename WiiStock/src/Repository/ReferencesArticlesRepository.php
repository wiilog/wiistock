<?php

namespace App\Repository;

use App\Entity\ReferencesArticles;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method ReferencesArticles|null find($id, $lockMode = null, $lockVersion = null)
 * @method ReferencesArticles|null findOneBy(array $criteria, array $orderBy = null)
 * @method ReferencesArticles[]    findAll()
 * @method ReferencesArticles[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ReferencesArticlesRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, ReferencesArticles::class);
    }

//    /**
//     * @return ReferencesArticles[] Returns an array of ReferencesArticles objects
//     */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('r.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?ReferencesArticles
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
