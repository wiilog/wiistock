<?php

namespace App\Service\Dashboard\DashboardComponentGenerator;

use App\Entity\Dashboard;
use App\Entity\Dashboard\Meter as DashboardMeter;
use App\Entity\Dispatch;
use App\Entity\Type\Type;
use App\Service\Dashboard\DashboardService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use WiiCommon\Helper\Stream;

class DailyDispatchesComponentGenerator implements DashboardComponentGenerator {

    public function __construct(
        private DashboardService $dashboardService,
    ) {
    }

    public function persist(EntityManagerInterface $entityManager,
                            Dashboard\Component    $component): void {
        $config = $component->getConfig();

        $dispatchStatusesFilter = $config['dispatchStatuses'] ?? [];
        $dispatchTypesFilter = $config['dispatchTypes'] ?? [];
        $scale = $config['scale'] ?? DashboardService::DEFAULT_DAILY_REQUESTS_SCALE;
        $period = $config['period'] ?? DashboardService::DAILY_PERIOD_PREVIOUS_DAYS;
        $date = $config['date'] ?? 'endDate';
        $separateType = isset($config['separateType']) && $config['separateType'];

        $type = match ($date) {
            'treatmentDate' => "de traitement",
            'startDate' => "d'échéances Du",
            'validationDate' => "de validation",
            default => "d'échéances Au", // endDate
        };

        $hint = "Nombre d'acheminements ayant leurs dates $type sur les jours présentés";

        $dispatchRepository = $entityManager->getRepository(Dispatch::class);
        $typeRepository = $entityManager->getRepository(Type::class);

        $chartData = $this->dashboardService->getDailyObjectsStatistics(
            $entityManager,
            $scale,
            function(DateTime $dateMin, DateTime $dateMax) use ($dispatchRepository, $dispatchStatusesFilter, $dispatchTypesFilter, $date, $separateType) {
                return $dispatchRepository->countByDates($dateMin, $dateMax, $separateType, $dispatchStatusesFilter, $dispatchTypesFilter, $date);
            },
            $period
        );

        if ($separateType) {
            $types = $typeRepository->findBy(['id' => $dispatchTypesFilter]);
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

        $chartData['hint'] = $hint;
        $meter = $this->dashboardService->persistDashboardMeter($entityManager, $component, DashboardMeter\Chart::class);
        $meter
            ->setData($chartData);
        if ($chartColors) {
            $meter->setChartColors($chartColors);
        }
    }
}
