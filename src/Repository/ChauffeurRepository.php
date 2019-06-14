<?php

namespace App\Repository;

use App\Entity\Chauffeur;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method Chauffeur|null find($id, $lockMode = null, $lockVersion = null)
 * @method Chauffeur|null findOneBy(array $criteria, array $orderBy = null)
 * @method Chauffeur[]    findAll()
 * @method Chauffeur[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ChauffeurRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, Chauffeur::class);
    }

    public function findAllSorted()
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
            "SELECT c FROM App\Entity\Chauffeur c
            ORDER BY c.nom
            "
        );

        return $query->execute();
    }
}
