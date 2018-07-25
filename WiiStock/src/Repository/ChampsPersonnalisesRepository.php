<?php

namespace App\Repository;

use App\Entity\ChampsPersonnalises;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method ChampsPersonnalises|null find($id, $lockMode = null, $lockVersion = null)
 * @method ChampsPersonnalises|null findOneBy(array $criteria, array $orderBy = null)
 * @method ChampsPersonnalises[]    findAll()
 * @method ChampsPersonnalises[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ChampsPersonnalisesRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, ChampsPersonnalises::class);
    }

//    /**
//     * @return ChampsPersonnalises[] Returns an array of ChampsPersonnalises objects
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
    public function findOneBySomeField($value): ?ChampsPersonnalises
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
