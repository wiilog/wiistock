<?php

namespace App\Repository\ScheduledTask;

use App\Entity\Zone;
use Doctrine\ORM\EntityRepository;

class PurchaseRequestPlanRepository extends EntityRepository {

    public function isZoneInPurchaseRequestPlan(Zone $zone): bool {
        return $this->createQueryBuilder('purchaseRequestPlan')
            ->select('COUNT(purchaseRequestPlan.id)')
            ->andWhere('zone.id = :zoneId')
            ->join('purchaseRequestPlan.zones', 'zone')
            ->setParameter('zoneId', $zone->getId())
            ->getQuery()
            ->getSingleScalarResult() > 0 ;
    }

}
