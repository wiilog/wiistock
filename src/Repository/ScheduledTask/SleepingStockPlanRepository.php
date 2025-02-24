<?php

namespace App\Repository\ScheduledTask;

use App\Entity\ScheduledTask\SleepingStockPlan;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SleepingStockPlan>
 */
class SleepingStockPlanRepository extends ServiceEntityRepository {

    public function __construct(ManagerRegistry $registry) {
        parent::__construct($registry, SleepingStockPlan::class);
    }
}
