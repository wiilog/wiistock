<?php

namespace App\Repository\FreeField;

use App\Entity\FreeField\FreeFieldManagementRule;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Connection;
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

    public function findByCategoryTypeLabels(array $categoryTypeLabels): array {
        return $this->createQueryBuilder('free_field_management_rule')
            ->join('free_field_management_rule.type', 'type')
            ->join('type.category', 'category')
            ->where('category.label IN (:categoryTypeLabels)')
            ->setParameter('categoryTypeLabels', $categoryTypeLabels)
            ->getQuery()
            ->execute();
    }
}
