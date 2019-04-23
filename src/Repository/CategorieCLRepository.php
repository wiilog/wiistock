<?php

namespace App\Repository;

use App\Entity\CategorieCL;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method CategorieCL|null find($id, $lockMode = null, $lockVersion = null)
 * @method CategorieCL|null findOneBy(array $criteria, array $orderBy = null)
 * @method CategorieCL[]    findAll()
 * @method CategorieCL[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CategorieCLRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, CategorieCL::class);
    }

    public function findByLabel($label)
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "SELECT c
            FROM App\Entity\CategorieCL c
            WHERE c.label = :label
           "
        )->setParameter('label', $label);

        return $query->getOneOrNullResult();
    }


    // /**
    //  * @return CategorieCL[] Returns an array of CategorieCL objects
    //  */
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
    public function findOneBySomeField($value): ?CategorieCL
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
