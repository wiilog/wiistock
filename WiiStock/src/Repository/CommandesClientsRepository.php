<?php

namespace App\Repository;

use App\Entity\CommandesClients;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method CommandesClients|null find($id, $lockMode = null, $lockVersion = null)
 * @method CommandesClients|null findOneBy(array $criteria, array $orderBy = null)
 * @method CommandesClients[]    findAll()
 * @method CommandesClients[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CommandesClientsRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, CommandesClients::class);
    }

//    /**
//     * @return CommandesClients[] Returns an array of CommandesClients objects
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
    public function findOneBySomeField($value): ?CommandesClients
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
