<?php

namespace App\Repository;

use App\Entity\DashboardChartMeter;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\NonUniqueResultException;

/**
 * @method DashboardChartMeter|null find($id, $lockMode = null, $lockVersion = null)
 * @method DashboardChartMeter|null findOneBy(array $criteria, array $orderBy = null)
 * @method DashboardChartMeter[]    findAll()
 * @method DashboardChartMeter[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class DashboardChartMeterRepository extends EntityRepository
{
    public function clearTable(): void {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "DELETE
            FROM App\Entity\DashboardChartMeter d
           "
        );
        $query->execute();
    }

    /**
     * @param string $dashboard
     * @param string $id
     * @return mixed
     * @throws NonUniqueResultException
     */
    public function findByDashboardAndId(string $dashboard, string $id) {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "SELECT d.chartColors, d.location, d.total, d.data
            FROM App\Entity\DashboardChartMeter d
            WHERE d.dashboard = :dashboard AND d.chartKey = :key
           "
        )->setParameters([
            'dashboard' => $dashboard,
            'key' => $id,
        ]);
        $meter = $query->getOneOrNullResult();

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
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "SELECT d
            FROM App\Entity\DashboardChartMeter d
            WHERE d.dashboard = :dashboard AND d.chartKey = :key
           "
        )->setParameters([
            'dashboard' => $dashboard,
            'key' => $id,
        ]);
        return $query->getOneOrNullResult();
    }
}
