<?php

namespace App\Repository;

use App\Entity\DashboardChartMeter;
use Doctrine\ORM\EntityRepository;

/**
 * @method DashboardChartMeter|null find($id, $lockMode = null, $lockVersion = null)
 * @method DashboardChartMeter|null findOneBy(array $criteria, array $orderBy = null)
 * @method DashboardChartMeter[]    findAll()
 * @method DashboardChartMeter[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class DashboardChartMeterRepository extends EntityRepository
{
}
