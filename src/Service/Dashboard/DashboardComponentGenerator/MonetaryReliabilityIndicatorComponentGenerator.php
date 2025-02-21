<?php

namespace App\Service\Dashboard\DashboardComponentGenerator;

use App\Entity\Dashboard;
use App\Entity\Dashboard\Meter as DashboardMeter;
use App\Entity\MouvementStock;
use App\Service\Dashboard\DashboardService;
use Doctrine\ORM\EntityManagerInterface;

class MonetaryReliabilityIndicatorComponentGenerator implements DashboardComponentGenerator {

    public function __construct(
        private DashboardService $dashboardService,
    ) {
    }

    public function persist(EntityManagerInterface $entityManager,
                            Dashboard\Component    $component): void {

        $stockMovementRepository = $entityManager->getRepository(MouvementStock::class);

        $firstDayOfCurrentMonth = date("Y-m-d", strtotime("first day of this month"));
        $totalEntryRefArticleOfThisMonth = $stockMovementRepository->countTotalEntryPriceRefArticle($firstDayOfCurrentMonth);
        $totalExitRefArticleOfThisMonth = $stockMovementRepository->countTotalExitPriceRefArticle($firstDayOfCurrentMonth);
        $totalRefArticleOfThisMonth = $totalEntryRefArticleOfThisMonth - $totalExitRefArticleOfThisMonth;
        $totalEntryArticleOfThisMonth = $stockMovementRepository->countTotalEntryPriceArticle($firstDayOfCurrentMonth);
        $totalExitArticleOfThisMonth = $stockMovementRepository->countTotalExitPriceArticle($firstDayOfCurrentMonth);
        $totalArticleOfThisMonth = $totalEntryArticleOfThisMonth - $totalExitArticleOfThisMonth;
        $count = $totalRefArticleOfThisMonth + $totalArticleOfThisMonth;

        $meter = $this->dashboardService->persistDashboardMeter($entityManager, $component, DashboardMeter\Indicator::class);
        $meter
            ->setCount($count ?? 0);
    }
}
