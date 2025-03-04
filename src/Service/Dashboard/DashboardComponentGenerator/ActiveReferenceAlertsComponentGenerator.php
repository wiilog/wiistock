<?php

namespace App\Service\Dashboard\DashboardComponentGenerator;

use App\Entity\Alert;
use App\Entity\Dashboard;
use App\Entity\Dashboard\Meter as DashboardMeter;
use App\Service\Dashboard\DashboardService;
use Doctrine\ORM\EntityManagerInterface;

class ActiveReferenceAlertsComponentGenerator implements DashboardComponentGenerator {

    public function __construct(
        private DashboardService $dashboardService,
    ) {
    }

    public function persist(EntityManagerInterface $entityManager,
                            Dashboard\Component    $component): void {
        $alertRepository = $entityManager->getRepository(Alert::class);

        $meter = $this->dashboardService->persistDashboardMeter($entityManager, $component, DashboardMeter\Indicator::class);
        $count = $alertRepository->countAllActiveByParams($component->getConfig());

        $meter
            ->setCount($count ?? 0);
    }
}
