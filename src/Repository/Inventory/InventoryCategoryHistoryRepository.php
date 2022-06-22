<?php

namespace App\Repository\Inventory;

use App\Entity\Inventory\InventoryCategoryHistory;
use Doctrine\ORM\EntityRepository;

/**
 * @method InventoryCategoryHistory|null find($id, $lockMode = null, $lockVersion = null)
 * @method InventoryCategoryHistory|null findOneBy(array $criteria, array $orderBy = null)
 * @method InventoryCategoryHistory[]    findAll()
 * @method InventoryCategoryHistory[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class InventoryCategoryHistoryRepository extends EntityRepository
{
}
