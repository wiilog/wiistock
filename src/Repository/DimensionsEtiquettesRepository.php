<?php

namespace App\Repository;

use App\Entity\DimensionsEtiquettes;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method DimensionsEtiquettes|null find($id, $lockMode = null, $lockVersion = null)
 * @method DimensionsEtiquettes|null findOneBy(array $criteria, array $orderBy = null)
 * @method DimensionsEtiquettes[]    findAll()
 * @method DimensionsEtiquettes[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class DimensionsEtiquettesRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, DimensionsEtiquettes::class);
    }

    public function getOneDimension()
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "SELECT de
            FROM App\Entity\DimensionsEtiquettes de
            "
        );
        return $query->getOneOrNullResult();
    }

    // /**
    //  * @return DimensionsEtiquettes[] Returns an array of DimensionsEtiquettes objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('d.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?DimensionsEtiquettes
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
