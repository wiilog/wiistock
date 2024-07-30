<?php

namespace App\Repository\FreeField;

use App\Entity\FreeField\FreeFieldManagementRule;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<FreeFieldManagementRule>
 */
class FreeFielsManagementRuleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FreeFieldManagementRule::class);
    }
}
