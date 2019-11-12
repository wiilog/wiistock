<?php

namespace App\Repository;

use App\Entity\ParamInventory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method ParamInventory|null find($id, $lockMode = null, $lockVersion = null)
 * @method ParamInventory|null findOneBy(array $criteria, array $orderBy = null)
 * @method ParamInventory[]    findAll()
 * @method ParamInventory[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ParamInventoryRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, ParamInventory::class);
    }

}
