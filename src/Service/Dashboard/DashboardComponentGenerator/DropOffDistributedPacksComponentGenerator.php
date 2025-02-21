<?php

namespace App\Service\Dashboard\DashboardComponentGenerator;

use App\Entity\Dashboard;
use App\Entity\Dashboard\Meter as DashboardMeter;
use App\Entity\Tracking\TrackingMovement;
use App\Service\Dashboard\DashboardService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;

class DropOffDistributedPacksComponentGenerator implements DashboardComponentGenerator {

    public function __construct(
        private DashboardService $dashboardService,
    ) {
    }

    public function persist(EntityManagerInterface $entityManager,
                            Dashboard\Component    $component): void {
        $trackingMovementRepository = $entityManager->getRepository(TrackingMovement::class);

        $config = $component->getConfig();
        $clusterKey = 'locations';
        $this->dashboardService->updateComponentLocationCluster($entityManager, $component, $clusterKey);
        $locationCluster = $component->getLocationCluster($clusterKey);
        $entityManager->flush();
        $packsCountByDays = $this->dashboardService->getDailyObjectsStatistics($entityManager, DashboardService::DEFAULT_WEEKLY_REQUESTS_SCALE,
            function (DateTime $date) use ($trackingMovementRepository, $locationCluster) {
                return $trackingMovementRepository->countDropsOnLocationsOn($date, $locationCluster->getLocations()->toArray());
            });

        $chartColors = $config['chartColors'] ?? [Dashboard\ComponentType::DEFAULT_CHART_COLOR];

        $meter = $this->dashboardService->persistDashboardMeter($entityManager, $component, DashboardMeter\Chart::class);
        $meter
            ->setData($packsCountByDays)
            ->setChartColors($chartColors);
    }
}
