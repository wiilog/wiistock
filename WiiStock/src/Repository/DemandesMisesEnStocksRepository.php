<?php

namespace App\Repository;

use App\Entity\DemandesMisesEnStocks;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method DemandesMisesEnStocks|null find($id, $lockMode = null, $lockVersion = null)
 * @method DemandesMisesEnStocks|null findOneBy(array $criteria, array $orderBy = null)
 * @method DemandesMisesEnStocks[]    findAll()
 * @method DemandesMisesEnStocks[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class DemandesMisesEnStocksRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, DemandesMisesEnStocks::class);
    }

//    /**
//     * @return DemandesMisesEnStocks[] Returns an array of DemandesMisesEnStocks objects
//     */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('d.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?DemandesMisesEnStocks
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
