<?php

namespace App\Repository\ScheduledTask;

use App\Entity\ScheduledTask\SleepingStockPlan;
use Doctrine\ORM\EntityRepository;

/**
 * @extends EntityRepository<SleepingStockPlan>
 */
class SleepingStockPlanRepository extends EntityRepository {
    /**
     * @return array<int, SleepingStockPlan>
     */
    public function findScheduled(): array {
        return $this->createQueryBuilder("sleeping_stock_plan")
            // TODO WIIS-12522 : and where active = true
            ->getQuery()
            ->getResult();
    }
}
