<?php

namespace App\Service\Dashboard\DashboardComponentGenerator;

use App\Entity\Dashboard;
use App\Entity\Dashboard\Meter as DashboardMeter;
use App\Entity\Handling;
use App\Service\Dashboard\DashboardService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;

class HandlingTrackingComponentGenerator implements DashboardComponentGenerator {

    public function __construct(
        private DashboardService   $dashboardService,
    ) {
    }

    public function persist(EntityManagerInterface $entityManager,
                            Dashboard\Component    $component): void {
        $config = $component->getConfig();

        $handlingTypes = $config['handlingTypes'] ?? [];
        $scale = $config['scale'] ?? DashboardService::DEFAULT_HANDLING_TRACKING_SCALE;
        $period = $config['period'] ?? DashboardService::DAILY_PERIOD_PREVIOUS_DAYS;
        $dates = [];

        if(!empty($config['creationDate'])){
            $dates[] = 'creationDate';
        }
        if(!empty($config['desiredDate'])){
            $dates[] = 'desiredDate';
        }
        if(!empty($config['validationDate'])){
            $dates[] = 'validationDate';
        }

        $hint = $config['tooltip'] ?? '';

        $handlingRepository = $entityManager->getRepository(Handling::class);

        $chartData = [];
        $labels = [
            'validationDate' => 'Date de traitement',
            'desiredDate' => 'Date attendue',
            'creationDate' => 'Date de création',
        ];
        foreach ($dates as $date){
            $dateData = $this->dashboardService->getDailyObjectsStatistics(
                $entityManager,
                $scale,
                function(DateTime $dateMin, DateTime $dateMax) use ($date, $handlingRepository, $handlingTypes) {
                    return $handlingRepository->countByDates($dateMin, $dateMax, [
                        'handlingTypesFilter' => $handlingTypes,
                        'date' => $date
                    ]);
                },
                $period
            );
            $label = $labels[$date];
            foreach ($dateData as $dateKey => $datum) {
                if (!isset($chartData[$dateKey][$label])) {
                    $chartData[$dateKey][$label] = 0;
                }
                $chartData[$dateKey][$label] += intval($datum);
            }
        }

        $chartColors = $config['chartColors'] ?? [];

        $chartData['hint'] = $hint;
        $meter = $this->dashboardService->persistDashboardMeter($entityManager, $component, DashboardMeter\Chart::class);
        $meter
            ->setData($chartData);
        if ($chartColors) {
            $meter->setChartColors($chartColors);
        }
    }
}
