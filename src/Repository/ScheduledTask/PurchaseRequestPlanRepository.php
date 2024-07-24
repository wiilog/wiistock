<?php

namespace App\Repository\ScheduledTask;

use App\Entity\ScheduledTask\PurchaseRequestPlan;
use App\Entity\Zone;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;

class PurchaseRequestPlanRepository extends EntityRepository implements ScheduledTaskRepository{

    public function isZoneInPurchaseRequestPlan(Zone $zone): bool {
        return $this->createQueryBuilder('purchaseRequestPlan')
            ->select('COUNT(purchaseRequestPlan.id)')
            ->andWhere('zone.id = :zoneId')
            ->join('purchaseRequestPlan.zones', 'zone')
            ->setParameter('zoneId', $zone->getId())
            ->getQuery()
            ->getSingleScalarResult() > 0 ;
    }

    public function countScheduled(): int {
        return $this->createScheduledQueryBuilder()
            ->select("COUNT(purchaseRequestPlan)")
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return PurchaseRequestPlan[]
     */
    public function findScheduled(): array {
        return $this->createScheduledQueryBuilder()
            ->getQuery()
            ->getResult();
    }

    private function createScheduledQueryBuilder(): QueryBuilder {
        return $this->createQueryBuilder('purchaseRequestPlan');
    }
}
