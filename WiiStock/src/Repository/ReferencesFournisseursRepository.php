<?php

namespace App\Repository;

use App\Entity\ReferencesFournisseurs;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method ReferencesFournisseurs|null find($id, $lockMode = null, $lockVersion = null)
 * @method ReferencesFournisseurs|null findOneBy(array $criteria, array $orderBy = null)
 * @method ReferencesFournisseurs[]    findAll()
 * @method ReferencesFournisseurs[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ReferencesFournisseursRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, ReferencesFournisseurs::class);
    }

//    /**
//     * @return ReferencesFournisseurs[] Returns an array of ReferencesFournisseurs objects
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
    public function findOneBySomeField($value): ?ReferencesFournisseurs
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
