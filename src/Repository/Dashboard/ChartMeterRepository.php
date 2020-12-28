<?php

namespace App\Repository\Dashboard;

use App\Entity\Dashboard\Meter as DashboardMeter;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\NonUniqueResultException;

/**
 * @method DashboardMeter\Chart|null find($id, $lockMode = null, $lockVersion = null)
 * @method DashboardMeter\Chart|null findOneBy(array $criteria, array $orderBy = null)
 * @method DashboardMeter\Chart[]    findAll()
 * @method DashboardMeter\Chart[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ChartMeterRepository extends EntityRepository
{
    public function clearTable(): void {
        $this->createQueryBuilder('chart_meter')
            ->delete('chart_meter')
            ->getQuery()
            ->execute();
    }

    /**
     * @param string $dashboard
     * @param string $id
     * @return mixed
     * @throws NonUniqueResultException
     */
    public function findByDashboardAndId(string $dashboard, string $id) {

        $meter = $this->createQueryBuilder('chart_meter')
            ->select('chart_meter.chartColors')
            ->addSelect('chart_meter.location')
            ->addSelect('chart_meter.total')
            ->addSelect('chart_meter.data')
            ->where('chart_meter.dashboard = :dashboard AND chart_meter.chartKey = :key')
            ->setParameters([
                'dashboard' => $dashboard,
                'key' => $id,
            ])
            ->getQuery()
            ->getOneOrNullResult();

        if (!empty($meter) && !empty($meter['data'])) {
            $meter['data'] = array_reduce($meter['data'], function (array $carry, $item) {
                $carry[$item['dataKey']] = $item['data'];
                return $carry;
            }, []);
        }

        return $meter;
    }

    /**
     * @param string $dashboard
     * @param string $id
     * @return mixed
     * @throws NonUniqueResultException
     */
    public function findEntityByDashboardAndId(string $dashboard, string $id) {

        return $this->createQueryBuilder('chart_meter')
            ->where('chart_meter.dashboard = :dashboard AND chart_meter.chartKey = :key')
            ->setParameters([
                'dashboard' => $dashboard,
                'key' => $id,
            ])
            ->getQuery()
            ->getOneOrNullResult();
    }
}
