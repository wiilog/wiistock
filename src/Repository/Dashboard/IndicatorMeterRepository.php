<?php

namespace App\Repository\Dashboard;

use App\Entity\Dashboard\Meter as DashboardMeter;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\NonUniqueResultException;

/**
 * @method DashboardMeter\Indicator|null find($id, $lockMode = null, $lockVersion = null)
 * @method DashboardMeter\Indicator|null findOneBy(array $criteria, array $orderBy = null)
 * @method DashboardMeter\Indicator[]    findAll()
 * @method DashboardMeter\Indicator[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class IndicatorMeterRepository extends EntityRepository
{
    public function clearTable(): void {
        $this->createQueryBuilder('indicator_meter')
            ->delete('indicator_meter')
            ->getQuery()
            ->execute();
    }

    /**
     * @param string $dashboard
     * @return array
     */
    public function getByDashboard(string $dashboard): array {
        return $this->createQueryBuilder('indicator_meter')
            ->select('indicator_meter.label')
            ->addSelect('indicator_meter.count')
            ->addSelect('indicator_meter.delay')
            ->addSelect('indicator_meter.meterKey')
            ->where('indicator_meter.dashboard = :dashboard')
            ->setParameter('dashboard', $dashboard)
            ->getQuery()
            ->execute();
    }

    /**
     * @param string $key
     * @param string $dashboard
     * @return mixed
     * @throws NonUniqueResultException
     */
    public function findByKeyAndDashboard(string $key, string $dashboard) {
        return $this->createQueryBuilder('indicator_meter')
            ->select('indicator_meter.label')
            ->addSelect('indicator_meter.count')
            ->addSelect('indicator_meter.delay')
            ->addSelect('indicator_meter.meterKey')
            ->where('indicator_meter.dashboard = :dashboard AND indicator_meter.meterKey = :key')
            ->setParameters([
                'dashboard' => $dashboard,
                'key' => $key,
            ])
            ->getQuery()
            ->getOneOrNullResult();
    }
}
