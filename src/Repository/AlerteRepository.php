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

    // Utilisé dans l'accueil
    public function countAlertes()
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "SELECT COUNT(a)
            FROM App\Entity\Alerte a
            WHERE a.SeuilAtteint = TRUE "
        );
        ;
        return $query->execute(); 
    }

}
