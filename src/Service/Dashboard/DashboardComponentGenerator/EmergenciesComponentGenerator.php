<?php

namespace App\Service\Dashboard\DashboardComponentGenerator;

use App\Entity\Dashboard;
use App\Entity\Dashboard\Meter as DashboardMeter;
use App\Entity\Emergency\Emergency;
use App\Service\Dashboard\DashboardService;
use Doctrine\ORM\EntityManagerInterface;

class EmergenciesComponentGenerator implements DashboardComponentGenerator {

    public function __construct(
        private DashboardService $dashboardService,
    ) {
    }

    public function persist(EntityManagerInterface $entityManager,
                            Dashboard\Component    $component): void {
        $componentType = $component->getType();
        $meterKey = $componentType->getMeterKey();

        $config = $component->getConfig();
        $emergencyTypeIds = $config['emergencyTypes'] ?? [];

        $daily = $meterKey === Dashboard\ComponentType::DAILY_ARRIVALS_EMERGENCIES;
        $active = $meterKey === Dashboard\ComponentType::ARRIVALS_EMERGENCIES_TO_RECEIVE;

        $meter = $this->dashboardService->persistDashboardMeter($entityManager, $component, DashboardMeter\Indicator::class);

        $emergencyRepository = $entityManager->getRepository(Emergency::class);
        $unsolvedEmergencies = $emergencyRepository->countUntriggered($daily, $active, $emergencyTypeIds);
        $meter
            ->setCount($unsolvedEmergencies ?? 0);
    }
}
