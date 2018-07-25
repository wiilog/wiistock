<?php

namespace App\Repository;

use App\Entity\CommandesFournisseurs;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method CommandesFournisseurs|null find($id, $lockMode = null, $lockVersion = null)
 * @method CommandesFournisseurs|null findOneBy(array $criteria, array $orderBy = null)
 * @method CommandesFournisseurs[]    findAll()
 * @method CommandesFournisseurs[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CommandesFournisseursRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, CommandesFournisseurs::class);
    }

//    /**
//     * @return CommandesFournisseurs[] Returns an array of CommandesFournisseurs objects
//     */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('c.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?CommandesFournisseurs
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
