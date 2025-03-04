<?php

namespace App\Service\Dashboard\DashboardComponentGenerator;

use App\Entity\Dashboard;
use App\Entity\Dashboard\Meter as DashboardMeter;
use App\Entity\Dispute;
use App\Service\Dashboard\DashboardService;
use Doctrine\ORM\EntityManagerInterface;

class DisputeToTreatComponentGenerator implements DashboardComponentGenerator {

    public function __construct(
        private DashboardService $dashboardService,
    ) {
    }

    public function persist(EntityManagerInterface $entityManager,
                            Dashboard\Component    $component): void {

        $config = $component->getConfig();
        $disputeTypes = $config['disputeTypes'];
        $disputeStatuses = $config['disputeStatuses'];
        $disputeEmergency = $config['disputeEmergency'] ?? false;

        $disputeRepository = $entityManager->getRepository(Dispute::class);
        $count = $disputeRepository->countByFilters([
            'types' => $disputeTypes,
            'statuses' => $disputeStatuses,
            'disputeEmergency' => $disputeEmergency,
        ]);

        $meter = $this->dashboardService->persistDashboardMeter($entityManager, $component, DashboardMeter\Indicator::class);
        $meter->setCount($count ?? 0);
    }
}
