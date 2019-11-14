<?php

namespace App\Entity;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method LitigeHistoric|null find($id, $lockMode = null, $lockVersion = null)
 * @method LitigeHistoric|null findOneBy(array $criteria, array $orderBy = null)
 * @method LitigeHistoric[]    findAll()
 * @method LitigeHistoric[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class LitigeHistoricRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, LitigeHistoric::class);
    }

}
