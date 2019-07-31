<?php

namespace App\Repository;

use App\Entity\PrefixeNomDemande;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method PrefixeNomDemande|null find($id, $lockMode = null, $lockVersion = null)
 * @method PrefixeNomDemande|null findOneBy(array $criteria, array $orderBy = null)
 * @method PrefixeNomDemande[]    findAll()
 * @method PrefixeNomDemande[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PrefixeNomDemandeRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, PrefixeNomDemande::class);
    }

    // /**
    //  * @return PrefixeNomDemande[] Returns an array of PrefixeNomDemande objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('p.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?PrefixeNomDemande
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
