<?php

namespace App\Service\Dashboard\DashboardComponentGenerator;

use App\Entity\Dashboard;
use App\Entity\Dashboard\Meter as DashboardMeter;
use App\Entity\Handling;
use App\Entity\Type;
use App\Service\Dashboard\DashboardService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use WiiCommon\Helper\Stream;

class DailyHandlingOrOperationsComponentGenerator implements DashboardComponentGenerator {

    public function __construct(
        private DashboardService $dashboardService,
    ) {
    }

    public function persist(EntityManagerInterface $entityManager,
                            Dashboard\Component    $component): void {
        $config = $component->getConfig();
        $isOperations = $component->getType() && $component->getType()->getMeterKey() === Dashboard\ComponentType::DAILY_OPERATIONS;
        $handlingStatusesFilter = $config['handlingStatuses'] ?? [];
        $handlingTypesFilter = $config['handlingTypes'] ?? [];
        $scale = $config['daysNumber'] ?? DashboardService::DEFAULT_DAILY_REQUESTS_SCALE;
        $period = $config['period'] ?? DashboardService::DAILY_PERIOD_PREVIOUS_DAYS;
        $separateType = isset($config['separateType']) && $config['separateType'];

        $handlingRepository = $entityManager->getRepository(Handling::class);
        $typeRepository = $entityManager->getRepository(Type::class);

        $chartData = $this->dashboardService->getDailyObjectsStatistics(
            $entityManager,
            $scale,
            function(DateTime $dateMin, DateTime $dateMax) use ($handlingRepository, $handlingStatusesFilter, $handlingTypesFilter, $separateType, $isOperations) {
                return $handlingRepository->countByDates(
                    $dateMin,
                    $dateMax,
                    [
                        'groupByTypes' => $separateType,
                        'isOperations' => $isOperations,
                        'handlingStatusesFilter' => $handlingStatusesFilter,
                        'handlingTypesFilter' => $handlingTypesFilter
                    ]
                );
            },
            $period
        );
        if ($separateType) {
            $types = $typeRepository->findBy(['id' => $handlingTypesFilter]);
            $chartData = Stream::from($chartData)
                ->reduce(function ($carry, $data, $date) use ($types) {
                    foreach ($types as $type) {
                        $carry[$date][$type->getLabel()] = 0;
                    }
                    foreach ($data as $datum) {
                        if (isset($datum['typeLabel']) && $datum['typeLabel']) {
                            $carry[$date][$datum['typeLabel']] = $datum['count'];
                        }
                    }
                    return $carry;
                }, []);
        }
        $chartColors = $config['chartColors'] ?? [];

        $meter = $this->dashboardService->persistDashboardMeter($entityManager, $component, DashboardMeter\Chart::class);
        $meter
            ->setData($chartData);
        if ($chartColors) {
            $meter->setChartColors($chartColors);
        }

    }
}
