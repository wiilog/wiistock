<?php

namespace App\Repository;

use App\Entity\Receptions;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method Receptions|null find($id, $lockMode = null, $lockVersion = null)
 * @method Receptions|null findOneBy(array $criteria, array $orderBy = null)
 * @method Receptions[]    findAll()
 * @method Receptions[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ReceptionsRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, Receptions::class);
    }

    public function findByDateOrStatut($date)
    {
        $entityManager = $this->getEntityManager();
        //formatage de la date pour l'utilisé => 2019-01-22%
        $dateF = date_format($date, 'Y-m-d ');
        $dateF = $dateF . '%';
        //récupération des champs selon la date du jour ou selon un statut spécifique  
        $query = $entityManager->createQuery(
            "SELECT r
            FROM App\Entity\Receptions r
            WHERE r.Statut = 6 OR r.date LIKE :date "
        )->setParameter('date', $dateF);
        ;
        return $query->execute(); 
    }

    public function findForIndex()
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "SELECT r.id, r.date, r.numeroReception, r.dateAttendu, s.nom as statut, f.nom as fournisseur
            FROM App\Entity\Receptions r
            JOIN r.Statut s JOIN r.fournisseur f
           "
        );
        ;
        return $query->execute(); 
    }

//    /**
//     * @return Receptions[] Returns an array of Receptions objects
//     */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('r.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?Receptions
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
