<?php

namespace App\Repository;

use App\Entity\InventoryFrequency;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method InventoryFrequency|null find($id, $lockMode = null, $lockVersion = null)
 * @method InventoryFrequency|null findOneBy(array $criteria, array $orderBy = null)
 * @method InventoryFrequency[]    findAll()
 * @method InventoryFrequency[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class InventoryFrequencyRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, InventoryFrequency::class);
    }

}
