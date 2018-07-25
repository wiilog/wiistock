<?php

namespace App\Repository;

use App\Entity\DemandesApprovisionnements;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method DemandesApprovisionnements|null find($id, $lockMode = null, $lockVersion = null)
 * @method DemandesApprovisionnements|null findOneBy(array $criteria, array $orderBy = null)
 * @method DemandesApprovisionnements[]    findAll()
 * @method DemandesApprovisionnements[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class DemandesApprovisionnementsRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, DemandesApprovisionnements::class);
    }

//    /**
//     * @return DemandesApprovisionnements[] Returns an array of DemandesApprovisionnements objects
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
    public function findOneBySomeField($value): ?DemandesApprovisionnements
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
