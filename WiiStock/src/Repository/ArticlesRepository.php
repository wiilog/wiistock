<?php

namespace App\Repository;

use App\Entity\Articles;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method Articles|null find($id, $lockMode = null, $lockVersion = null)
 * @method Articles|null findOneBy(array $criteria, array $orderBy = null)
 * @method Articles[]    findAll()
 * @method Articles[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ArticlesRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, Articles::class);
    }

    /**
     * @return Articles[] Returns an array of Articles objects
     */
    public function findByFilters($zone, $quai, $libelle, $numero, $searchPhrase, $sort)
    {
        $qb = $this->createQueryBuilder('article');
        $parameters = [];
        $key_id = 0;

        if ($zone) {
            $query = "";
            foreach ($zone as $key => $value) {
                $query = $query . "article.zone = ?" . $key_id . " OR ";
                $parameters[$key_id] = $value;
                $key_id += 1;
            }
            $query = substr($query, 0, -4);
            $qb->andWhere($query)
            ->setParameters($parameters);
        }

        if ($quai) {
            $query = "";
            foreach ($quai as $key => $value) {
                $query = $query . "article.quai = ?" . $key_id . " OR ";
                $parameters[$key_id] = $value;
                $key_id += 1;
            }
            $query = substr($query, 0, -4);
            $qb->andWhere($query)
            ->setParameters($parameters);
        }

        if ($libelle != "") {
            $qb->andWhere('article.libelle LIKE :libelle')
                ->setParameter('libelle', '%' . $libelle . '%');
        }

        if ($numero != "") {
            $qb->andWhere('article.n LIKE :numero')
                ->setParameter('numero', '%' . $numero . '%');
        }

        if ($searchPhrase != "") {
            $qb->andWhere('article.statut LIKE :search
                OR article.n LIKE :search
                OR article.libelle LIKE :search
                OR article.quantite LIKE :search
            ')
                ->setParameter('search', '%' . $searchPhrase . '%');
        }

        if ($sort) {
            foreach ($sort as $key => $value) {
                $qb->orderBy('article.' . $key, $value);
            }
        } else {
            $qb->orderBy('article.date_comptabilisation', 'ASC');
        }

        return $qb;
    }

    public function findByDesignation($value)
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.designation like :val')
            ->setParameter('val', '%' . $value . '%')
            ->orderBy('r.id', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

//    /**
//     * @return Articles[] Returns an array of Articles objects
//     */
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
    public function findOneBySomeField($value): ?Articles
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
