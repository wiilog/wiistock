<?php

namespace App\Repository;

use App\Entity\Transporteur;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Transporteur|null find($id, $lockMode = null, $lockVersion = null)
 * @method Transporteur|null findOneBy(array $criteria, array $orderBy = null)
 * @method Transporteur[]    findAll()
 * @method Transporteur[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TransporteurRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Transporteur::class);
    }

    public function findAllSorted()
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
            "SELECT t FROM App\Entity\Transporteur t
            ORDER BY t.label
            "
        );

        return $query->execute();
    }

    public function getIdAndLibelleBySearch($search)
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
            "SELECT e.id, e.label as text
          FROM App\Entity\Transporteur e
          WHERE e.label LIKE :search"
        )->setParameter('search', '%' . $search . '%');

        return $query->execute();
    }



}
