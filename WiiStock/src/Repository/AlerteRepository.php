<?php

namespace App\Repository;

use App\Entity\Alerte;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method Alerte|null find($id, $lockMode = null, $lockVersion = null)
 * @method Alerte|null findOneBy(array $criteria, array $orderBy = null)
 * @method Alerte[]    findAll()
 * @method Alerte[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class AlerteRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, Alerte::class);
    }

    /* Récupération des alertes utilisateurs avec DQL */

    /* public function findAlerteByUser($user)
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "SELECT a
            FROM App\Entity\Alerte a
            WHERE a.AlerteUtilisateur = :user "
        )->setParameter('user', $user);
        ;
        return $query->execute(); 
    } */

    /* Récupération des alertes utilisateurs avec QueryBuilder */

    public function findAlerteByUser($user, AlerteRepository $repository)
    {
        return $query = $repository
            ->createQueryBuilder('a')
            ->where('a.AlerteUtilisateur = :user')
            ->setParameter('user', $user)
            ->getQuery();
    }

    /* Knp Paginator */

    /**
     * @return Query
     */

    public function paginate(): Query
    {
        return $this->findVisibleQuery()
            ->getQuery();
    }

    // /**
    //  * @return Alerte[] Returns an array of Alerte objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('a.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?Alerte
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
