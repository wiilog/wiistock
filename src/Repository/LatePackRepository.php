<?php

namespace App\Repository;

use App\Entity\LatePack;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Persistence\ManagerRegistry;

/**
 * @method LatePack|null find($id, $lockMode = null, $lockVersion = null)
 * @method LatePack|null findOneBy(array $criteria, array $orderBy = null)
 * @method LatePack[]    findAll()
 * @method LatePack[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class LatePackRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LatePack::class);
    }

    public function clearTable(): void {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "DELETE
            FROM App\Entity\LatePack l
           "
        );
        $query->execute();
    }

    public function findAllForDatatable() {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "SELECT l.delay, l.colis, l.date, l.emp
            FROM App\Entity\LatePack l
           "
        );
        return $query->execute();
    }

    // /**
    //  * @return LatePack[] Returns an array of LatePack objects
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
    public function findOneBySomeField($value): ?LatePack
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
