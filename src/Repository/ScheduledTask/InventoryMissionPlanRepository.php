<?php

namespace App\Repository\ScheduledTask;

use App\Entity\ScheduledTask\InventoryMissionPlan;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;

class InventoryMissionPlanRepository extends EntityRepository implements ScheduledTaskRepository {

    public function countScheduled(): int {
        return $this->createScheduledQueryBuilder()
            ->select("COUNT(inventoryMissionPlan)")
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return InventoryMissionPlan[]
     */
    public function findScheduled(): array {
        return $this->createScheduledQueryBuilder()
            ->getQuery()
            ->getResult();
    }

    private function createScheduledQueryBuilder(): QueryBuilder {
        return $this->createQueryBuilder('inventoryMissionPlan')
            ->andWhere("inventoryMissionPlan.active = true");
    }
}
