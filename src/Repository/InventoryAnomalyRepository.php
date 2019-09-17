<?php

namespace App\Repository;

use App\Entity\InventoryAnomaly;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method InventoryAnomaly|null find($id, $lockMode = null, $lockVersion = null)
 * @method InventoryAnomaly|null findOneBy(array $criteria, array $orderBy = null)
 * @method InventoryAnomaly[]    findAll()
 * @method InventoryAnomaly[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class InventoryAnomalyRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, InventoryAnomaly::class);
    }
}
