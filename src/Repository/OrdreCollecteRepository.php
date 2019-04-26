<?php

namespace App\Repository;

use App\Entity\OrdreCollecte;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method OrdreCollecte|null find($id, $lockMode = null, $lockVersion = null)
 * @method OrdreCollecte|null findOneBy(array $criteria, array $orderBy = null)
 * @method OrdreCollecte[]    findAll()
 * @method OrdreCollecte[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class OrdreCollecteRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, OrdreCollecte::class);
    }

    // /**
    //  * @return OrdreCollecte[] Returns an array of OrdreCollecte objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('o')
            ->andWhere('o.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('o.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?OrdreCollecte
    {
        return $this->createQueryBuilder('o')
            ->andWhere('o.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
    public function findOneByLivraison($collecte)
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            'SELECT a
            FROM App\Entity\OrdreCollecte a
            WHERE a.collecte = :collecte'
        )->setParameter('collecte', $collecte);
        return $query->getOneOrNullResult();
    }
}
