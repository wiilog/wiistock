<?php

namespace App\Repository;

use App\Entity\DashboardMeter;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;

/**
 * @method DashboardMeter|null find($id, $lockMode = null, $lockVersion = null)
 * @method DashboardMeter|null findOneBy(array $criteria, array $orderBy = null)
 * @method DashboardMeter[]    findAll()
 * @method DashboardMeter[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class DashboardMeterRepository extends EntityRepository
{
    public function clearTable(): void {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "DELETE
            FROM App\Entity\DashboardMeter d
           "
        );
        $query->execute();
    }

    /**
     * @param string $dashboard
     * @return array
     */
    public function getByDashboard(string $dashboard): array {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "
                SELECT d.label, d.count, d.delay, d.meterKey
                FROM App\Entity\DashboardMeter d
                WHERE d.dashboard = :dashboard
           "
        )->setParameter('dashboard', $dashboard);
        return $query->execute();
    }

    /**
     * @param string $key
     * @param string $dashboard
     * @return mixed
     * @throws NonUniqueResultException
     */
    public function getByKeyAndDashboard(string $key, string $dashboard) {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "
                SELECT d
                FROM App\Entity\DashboardMeter d
                WHERE d.dashboard = :dashboard AND d.meterKey = :key
           "
        )->setParameters([
            'dashboard' => $dashboard,
            'key' => $key,
        ]);
        return $query->getOneOrNullResult();
    }
}
