<?php

namespace App\Repository\ScheduledTask\StorageRule;

use App\Entity\ScheduledTask\ScheduleRule\ExportScheduleRule;
use Doctrine\ORM\EntityRepository;

/**
 * @method ExportScheduleRule|null find($id, $lockMode = null, $lockVersion = null)
 * @method ExportScheduleRule|null findOneBy(array $criteria, array $orderBy = null)
 * @method ExportScheduleRule[]    findAll()
 * @method ExportScheduleRule[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ExportScheduleRuleRepository extends EntityRepository
{
}
