<?php

namespace App\Repository;

use App\Entity\ChampsLibre;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method ChampsLibre|null find($id, $lockMode = null, $lockVersion = null)
 * @method ChampsLibre|null findOneBy(array $criteria, array $orderBy = null)
 * @method ChampsLibre[]    findAll()
 * @method ChampsLibre[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ChampsLibreRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, ChampsLibre::class);
    }

    public function getByType($type)
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "SELECT c
            FROM App\Entity\ChampsLibre c 
            JOIN c.type t 
            WHERE t.id = :id"
        )->setParameter('id', $type);
        ;
        return $query->execute(); 
    }

    public function getLabelAndId()
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "SELECT c.label, c.id
            FROM App\Entity\ChampsLibre c 
            "
        );
        return $query->getResult(); 
    }

    public function getLabelByCategory($category)
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "SELECT c.label, c.id
            FROM App\Entity\ChampsLibre c 
            JOIN c.type t
            JOIN t.category z
            WHERE z.label = :category
            "
        )->setParameter('category', $category);
        return $query->getResult(); 
    }

    // /**
    //  * @return ChampsLibre[] Returns an array of ChampsLibre objects
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
    public function findOneBySomeField($value): ?ChampsLibre
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
