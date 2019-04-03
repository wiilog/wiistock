<?php

namespace App\Repository;

use App\Entity\CollecteReference;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method CollecteReference|null find($id, $lockMode = null, $lockVersion = null)
 * @method CollecteReference|null findOneBy(array $criteria, array $orderBy = null)
 * @method CollecteReference[]    findAll()
 * @method CollecteReference[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CollecteReferenceRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, CollecteReference::class);
    }
    public function getByCollecte($collecte)
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
            "SELECT cr
          FROM App\Entity\CollecteReference cr
          WHERE cr.collecte = :collecte"
        )->setParameter('collecte',  $collecte);

        return $query->execute();
    }


    
}


