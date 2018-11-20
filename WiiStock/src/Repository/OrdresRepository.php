<?php

namespace App\Repository;

use App\Entity\Ordres;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method Ordres|null find($id, $lockMode = null, $lockVersion = null)
 * @method Ordres|null findOneBy(array $criteria, array $orderBy = null)
 * @method Ordres[]    findAll()
 * @method Ordres[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class OrdresRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, Ordres::class);
    }

    public function findOrdresByFilters($type, $auteur, $from, $to)
    {
        $parameters = [];
        $key_id = 0;
        $qb = $this->createQueryBuilder('o');

        if ($type) {
            $query = "";
            foreach ($type as $key => $value) {
                $query = $query . "o.type = ?" . $key_id . " OR ";
                $parameters[$key_id] = $value;
                $key_id += 1;
            }
            $query = substr($query, 0, -4);
            $qb->andWhere($query)
            ->setParameters($parameters);
        }
        if ($auteur) {
            $qb->leftJoin('o.auteur', 'a')
            ->andWhere('a.username = :username')
            ->setParameter('username', $auteur);
        }

        if ($from && $to) {
            $qb->andWhere('o.date_ordre >= :from')
			->andWhere('o.date_ordre <= :to')
			->setParameter('from', $from)
			->setParameter('to', $to);
        }

        return $qb->getQuery()->getResult();
    }

//    /**
//     * @return Ordres[] Returns an array of Ordres objects
//     */
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
    public function findOneBySomeField($value): ?Ordres
    {
        return $this->createQueryBuilder('o')
            ->andWhere('o.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
