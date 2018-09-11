<?php

namespace App\Repository;

use App\Entity\Utilisateurs;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method Utilisateurs|null find($id, $lockMode = null, $lockVersion = null)
 * @method Utilisateurs|null findOneBy(array $criteria, array $orderBy = null)
 * @method Utilisateurs[]    findAll()
 * @method Utilisateurs[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class UtilisateursRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, Utilisateurs::class);
    }


    /**
     * @return Parcs[] Returns an array of Parcs objects
     */
    public function findBySearchSort($searchPhrase, $sort)
    {
        $qb = $this->createQueryBuilder('user');
        $parameters = [];
        $key_id = 0;

        if ($searchPhrase != "") {
            $qb->leftJoin('user.groupe', 'groupe')
                ->andWhere('user.username LIKE :search
                OR user.email LIKE :search
                OR groupe.nom LIKE :search
                OR user.roles LIKE :search
                OR user.lastLogin LIKE :search
            ')
                ->setParameter('search', '%' . $searchPhrase . '%');
        }

        if ($sort) {
            foreach ($sort as $key => $value) {
                $qb->orderBy('user.' . $key, $value);
            }
        } else {
            $qb->orderBy('user.lastLogin', 'ASC');
        }

        return $qb;
    }

//    /**
//     * @return Utilisateurs[] Returns an array of Utilisateurs objects
//     */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('u.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?Utilisateurs
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
