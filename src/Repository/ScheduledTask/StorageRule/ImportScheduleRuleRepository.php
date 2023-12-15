<?php

namespace App\Repository\ScheduledTask\StorageRule;

use App\Entity\ScheduledTask\ScheduleRule\ImportScheduleRule;
use Doctrine\ORM\EntityRepository;

/**
 * @method ImportScheduleRule|null find($id, $lockMode = null, $lockVersion = null)
 * @method ImportScheduleRule|null findOneBy(array $criteria, array $orderBy = null)
 * @method ImportScheduleRule[]    findAll()
 * @method ImportScheduleRule[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ImportScheduleRuleRepository extends EntityRepository
{
}
