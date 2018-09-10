<?php

namespace App\Repository;

use App\Entity\Parcs;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * @method Parcs|null find($id, $lockMode = null, $lockVersion = null)
 * @method Parcs|null findOneBy(array $criteria, array $orderBy = null)
 * @method Parcs[]    findAll()
 * @method Parcs[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ParcsRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, Parcs::class);
    }

    /**
     * @return Parcs[] Returns an array of Parcs objects
     */
    public function findByStateSiteImmatriculation($statut, $site, $immat, $searchPhrase, $sort)
    {
        $qb = $this->createQueryBuilder('parc');
        $parameters = [];
        $key_id = 0;

        if ($site) {
            $query = "";
            foreach ($site as $key => $value) {
                $query = $query . "site.nom = ?" . $key_id . " OR ";
                $parameters[$key_id] = $value;
                $key_id += 1;
            }
            $query = substr($query, 0, -4);
            $qb
                ->andWhere($query)
                ->setParameters($parameters);
        }

        if ($statut) {
            $query = "";
            foreach ($statut as $key => $value) {
                $query = $query . "parc.statut = ?" . $key_id . " OR ";
                $parameters[$key_id] = $value;
                $key_id += 1;
            }
            $query = substr($query, 0, -4);
            $qb->andWhere($query)
            ->setParameters($parameters);
        }

        if ($immat != "") {
            $qb->andWhere('parc.immatriculation = :valImmat OR parc.n_serie = :valImmat')
                ->setParameter('valImmat', $immat);
        }

        if ($searchPhrase != "" || $site) {
            $qb->leftJoin('parc.site', 'site');
        }

        if ($searchPhrase != "") {
            $qb->leftJoin('parc.marque', 'marque')
                ->andWhere('parc.statut LIKE :search
                OR parc.n_serie LIKE :search
                OR parc.n_parc LIKE :search
                OR marque.nom LIKE :search
                OR site.nom LIKE :search
            ')
                ->setParameter('search', '%' . $searchPhrase . '%');
        }

        if ($sort) {
            foreach ($sort as $key => $value) {
                $qb->orderBy('parc.' . $key, $value);
            }
        } else {
            $qb->orderBy('parc.statut', 'ASC');
        }

        return $qb;
    }

    public function findLast()
    {
        $qb = $this->createQueryBuilder('parc');
        $qb->andWhere('parc.statut = :value1 OR parc.statut = :value2 OR parc.statut = :value3');
        $qb->setParameters(['value1' => 'Actif', 'value2' => 'Demande sortie/transfert', 'value3' => 'Sorti']);
        $qb->setMaxResults(1);
        $qb->orderBy('parc.id', 'DESC');

        return $qb->getQuery()->getOneOrNullResult();
    }
    
//    /**
//     * @return Parcs[] Returns an array of Parcs objects
//     */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('p.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
     */

    /*
    public function findOneBySomeField($value): ?Parcs
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
     */
}
