<?php

namespace App\Repository;

use App\Entity\ExportScheduleRule;
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
