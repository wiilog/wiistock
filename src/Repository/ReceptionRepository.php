<?php

namespace App\Repository;

use App\Entity\Reception;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method Reception|null find($id, $lockMode = null, $lockVersion = null)
 * @method Reception|null findOneBy(array $criteria, array $orderBy = null)
 * @method Reception[]    findAll()
 * @method Reception[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ReceptionRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, Reception::class);
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
            FROM App\Entity\Reception r
            WHERE r.Statut = 6 OR r.date LIKE :date "
        )->setParameter('date', $dateF);
        ;
        return $query->execute(); 
    }

//    /**
//     * @return Reception[] Returns an array of Receptions objects
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
    public function findOneBySomeField($value): ?Reception
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
