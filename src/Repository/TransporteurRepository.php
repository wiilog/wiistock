<?php

namespace App\Repository;

use App\Entity\Transporteur;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method Transporteur|null find($id, $lockMode = null, $lockVersion = null)
 * @method Transporteur|null findOneBy(array $criteria, array $orderBy = null)
 * @method Transporteur[]    findAll()
 * @method Transporteur[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TransporteurRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
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
}
