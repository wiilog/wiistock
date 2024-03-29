<?php

namespace App\Repository\Inventory;

use App\Entity\Inventory\InventoryMissionRule;
use Doctrine\ORM\EntityRepository;

/**
 * @method InventoryMissionRule|null find($id, $lockMode = null, $lockVersion = null)
 * @method InventoryMissionRule|null findOneBy(array $criteria, array $orderBy = null)
 * @method InventoryMissionRule[]    findAll()
 * @method InventoryMissionRule[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class InventoryMissionRuleRepository extends EntityRepository {

}
