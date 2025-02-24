<?php

namespace App\Repository;

use App\Entity\SleepingStockRequestInformation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SleepingStockRequestInformation>
 */
class SleepingStockRequestInformationRepository extends ServiceEntityRepository {
    public function __construct(ManagerRegistry $registry) {
        parent::__construct($registry, SleepingStockRequestInformation::class);
    }
}
