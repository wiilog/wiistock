<?php

namespace App\Repository;

use App\Entity\Fournisseurs;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method Fournisseurs|null find($id, $lockMode = null, $lockVersion = null)
 * @method Fournisseurs|null findOneBy(array $criteria, array $orderBy = null)
 * @method Fournisseurs[]    findAll()
 * @method Fournisseurs[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class FournisseursRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, Fournisseurs::class);
    }

    public function findBySearch($value)
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.nom like :val OR r.code_reference like :val')
            ->setParameter('val', '%' . $value . '%')
            ->orderBy('r.nom', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }
//    /**
//     * @return Fournisseurs[] Returns an array of Fournisseurs objects
//     */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('f')
            ->andWhere('f.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('f.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?Fournisseurs
    {
        return $this->createQueryBuilder('f')
            ->andWhere('f.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
