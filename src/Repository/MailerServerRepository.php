<?php

namespace App\Repository;

use App\Entity\MailerServer;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method MailerServer|null find($id, $lockMode = null, $lockVersion = null)
 * @method MailerServer|null findOneBy(array $criteria, array $orderBy = null)
 * @method MailerServer[]    findAll()
 * @method MailerServer[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class MailerServerRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, MailerServer::class);
    }

    public function  getOneMailerServer()
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "SELECT m
            FROM App\Entity\MailerServer m
            "
        );
        return $query->getOneOrNullResult();
    }
  

    // /**
    //  * @return MailerServer[] Returns an array of MailerServer objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('m.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?MailerServer
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
