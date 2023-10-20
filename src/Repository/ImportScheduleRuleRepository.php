<?php

namespace App\Repository;

use App\Entity\ImportScheduleRule;
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
