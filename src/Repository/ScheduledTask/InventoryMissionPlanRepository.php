<?php

namespace App\Repository\ScheduledTask;

use App\Entity\ScheduledTask\InventoryMissionPlan;
use Doctrine\ORM\EntityRepository;

class InventoryMissionPlanRepository extends EntityRepository implements ScheduledTaskRepository {

    /**
     * @return InventoryMissionPlan[]
     */
    public function findScheduled(): array {
        return $this->createQueryBuilder('inventoryMissionPlan')
            ->andWhere("inventoryMissionPlan.active = true")
            ->getQuery()
            ->getResult();
    }
}
