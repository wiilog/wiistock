<?php

namespace App\Service\Dashboard\DashboardComponentGenerator;

use App\Entity\Dashboard;
use App\Entity\Dashboard\Meter as DashboardMeter;
use App\Entity\MouvementStock;
use App\Service\Dashboard\DashboardService;
use Doctrine\ORM\EntityManagerInterface;

class MonetaryReliabilityGraphComponentGenerator implements DashboardComponentGenerator {

    public function __construct(
        private DashboardService $dashboardService,
    ) {
    }

    public function persist(EntityManagerInterface $entityManager,
                            Dashboard\Component    $component): void {
        $meter = $this->dashboardService->persistDashboardMeter($entityManager, $component, DashboardMeter\Chart::class);
        $config = $component->getConfig();

        $mouvementStockRepository = $entityManager->getRepository(MouvementStock::class);

        $firstDayOfCurrentMonth = date("Y-m-d", strtotime("first day of this month"));
        $lastDayOfCurrentMonth = date("Y-m-d", strtotime("last day of this month", strtotime($firstDayOfCurrentMonth)));
        $precedentMonthFirst = $firstDayOfCurrentMonth;
        $precedentMonthLast = $lastDayOfCurrentMonth;
        $idx = 0;
        $value = [];
        $value['data'] = [];
        while ($idx !== 6) {
            $month = date("m", strtotime($precedentMonthFirst));
            $month = date("F", mktime(0, 0, 0, $month, 10));
            $totalEntryRefArticleOfPrecedentMonth = $mouvementStockRepository->countTotalEntryPriceRefArticle($precedentMonthFirst, $precedentMonthLast);
            $totalExitRefArticleOfPrecedentMonth = $mouvementStockRepository->countTotalExitPriceRefArticle($precedentMonthFirst, $precedentMonthLast);
            $totalRefArticleOfPrecedentMonth = $totalEntryRefArticleOfPrecedentMonth - $totalExitRefArticleOfPrecedentMonth;
            $totalEntryArticleOfPrecedentMonth = $mouvementStockRepository->countTotalEntryPriceArticle($precedentMonthFirst, $precedentMonthLast);
            $totalExitArticleOfPrecedentMonth = $mouvementStockRepository->countTotalExitPriceArticle($precedentMonthFirst, $precedentMonthLast);
            $totalArticleOfPrecedentMonth = $totalEntryArticleOfPrecedentMonth - $totalExitArticleOfPrecedentMonth;

            $nbrFiabiliteMonetaireOfPrecedentMonth = $totalRefArticleOfPrecedentMonth + $totalArticleOfPrecedentMonth;
            $month = str_replace(
                array('January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'),
                array('Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin', 'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre'),
                $month
            );
            $value['data'][$month] = $nbrFiabiliteMonetaireOfPrecedentMonth;
            $precedentMonthFirst = date("Y-m-d", strtotime("-1 month", strtotime($precedentMonthFirst)));
            $precedentMonthLast = date("Y-m-d", strtotime("last day of -1 month", strtotime($precedentMonthLast)));
            $idx += 1;
        }
        $values = array_reverse($value['data']);
        $chartColors = $config['chartColors'] ?? [];

        $meter
            ->setData($values)
            ->setChartColors($chartColors);
    }
}
