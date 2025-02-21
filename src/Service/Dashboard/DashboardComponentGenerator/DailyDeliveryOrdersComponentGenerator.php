<?php

namespace App\Service\Dashboard\DashboardComponentGenerator;

use App\Entity\Dashboard;
use App\Entity\Dashboard\Meter as DashboardMeter;
use App\Entity\Livraison;
use App\Service\Dashboard\DashboardService;
use App\Service\TranslationService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;

class DailyDeliveryOrdersComponentGenerator implements DashboardComponentGenerator {

    public function __construct(
        private DashboardService   $dashboardService,
        private TranslationService $translationService,
    ) {
    }

    public function persist(EntityManagerInterface $entityManager,
                            Dashboard\Component    $component): void {
        $config = $component->getConfig();
        $deliveryOrderStatusesFilter = $config['deliveryOrderStatuses'] ?? [];
        $deliveryOrderTypesFilter = $config['deliveryOrderTypes'] ?? [];
        $scale = $config['daysNumber'] ?? DashboardService::DEFAULT_DAILY_REQUESTS_SCALE;
        $period = $config['period'] ?? DashboardService::DAILY_PERIOD_PREVIOUS_DAYS;
        $date = $config['date'];

        $deliveryOrderRepository = $entityManager->getRepository(Livraison::class);

        $type = match ($config['date']) {
            'validationDate'  => "de validation",
            'treatmentDate'   => "de traitement",
            default           => "date attendue",
        };
        $hint = "Nombre d'" . mb_strtolower($this->translationService->translate("Ordre", "Livraison", "Ordre de livraison", false)) . " ayant leur $type sur les jours présentés";

        $chartData = $this->dashboardService->getDailyObjectsStatistics(
            $entityManager,
            $scale,
            function(DateTime $dateMin, DateTime $dateMax) use ($deliveryOrderRepository, $deliveryOrderStatusesFilter, $deliveryOrderTypesFilter, $date) {
                return $deliveryOrderRepository->countByDates(
                    $dateMin,
                    $dateMax,
                    [
                        'deliveryOrderStatusesFilter' => $deliveryOrderStatusesFilter,
                        'deliveryOrderTypesFilter' => $deliveryOrderTypesFilter,
                        'date' => $date
                    ]
                );
            },
            $period
        );

        if ($config['displayDeliveryOrderContentCheckbox'] && $scale) {
            $deliveryOrderContent = $this->getDeliveryOrderContent(
                $entityManager,
                $scale,
                $deliveryOrderStatusesFilter,
                $deliveryOrderTypesFilter,
                [
                    'displayDeliveryOrderContent' => $config['displayDeliveryOrderContent'],
                    'date' => $date,
                    'period' => $period
                ]
            );

            if ($deliveryOrderContent) {
                $chartData['stack'] = $deliveryOrderContent;
            }

            if(isset($config['chartColor1'])) {
                $chartData['stack'][0]['backgroundColor'] = $config['chartColor1'];
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

    private function getDeliveryOrderContent(EntityManagerInterface $entityManager,
                                            int $scale,
                                            array $deliveryOrderStatusesFilter,
                                            array $deliveryOrderTypesFilter,
                                            array $options = []): array {
        $deliveryOrderRepository = $entityManager->getRepository(Livraison::class);

        $contentCountByDay = $this->dashboardService->getDailyObjectsStatistics(
            $entityManager,
            $scale,
            function(DateTime $dateMin, DateTime $dateMax) use ($deliveryOrderRepository, $deliveryOrderStatusesFilter, $deliveryOrderTypesFilter, $options) {
                return $deliveryOrderRepository->countContentByDates($dateMin, $dateMax, $deliveryOrderStatusesFilter, $deliveryOrderTypesFilter, $options);
            },
            $options['period']
        );

        $contentStack[] = [
            'label' => 'UL',
            'backgroundColor' => '#E5E1E1',
            'stack' => 'stack',
            'data' => []
        ];

        foreach ($contentCountByDay as $contentCount) {
            $contentStack[0]['data'][] = $contentCount;
        }

        return array_values($contentStack);
    }
}
