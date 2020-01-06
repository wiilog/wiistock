<?php

namespace App\Repository;

use App\Entity\ParametrageGlobal;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method ParametrageGlobal|null find($id, $lockMode = null, $lockVersion = null)
 * @method ParametrageGlobal|null findOneBy(array $criteria, array $orderBy = null)
 * @method ParametrageGlobal[]    findAll()
 * @method ParametrageGlobal[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ParametrageGlobalRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ParametrageGlobal::class);
    }

    // /**
    //  * @return ParametrageGlobal[] Returns an array of ParametrageGlobal objects
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
    public function findOneBySomeField($value): ?ParametrageGlobal
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */

    /**
     * @param $label
     * @return ParametrageGlobal
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function findOneByLabel($label) {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "SELECT pg
            FROM App\Entity\ParametrageGlobal pg
            WHERE pg.label LIKE :label
            "
        )->setParameter('label', $label);
        return $query->getOneOrNullResult();
    }
}
