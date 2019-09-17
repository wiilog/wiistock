<?php

namespace App\Repository;

use App\Entity\InventoryEntry;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method InventoryEntry|null find($id, $lockMode = null, $lockVersion = null)
 * @method InventoryEntry|null findOneBy(array $criteria, array $orderBy = null)
 * @method InventoryEntry[]    findAll()
 * @method InventoryEntry[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class InventoryEntryRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, InventoryEntry::class);
    }

}
