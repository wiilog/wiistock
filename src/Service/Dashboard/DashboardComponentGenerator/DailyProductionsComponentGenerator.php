<?php

namespace App\Service\Dashboard\DashboardComponentGenerator;

use App\Entity\Dashboard;
use App\Entity\Dashboard\Meter as DashboardMeter;
use App\Entity\ProductionRequest;
use App\Entity\Type;
use App\Service\Dashboard\DashboardService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use WiiCommon\Helper\Stream;

class DailyProductionsComponentGenerator implements DashboardComponentGenerator {

    public function __construct(
        private DashboardService $dashboardService,
    ) {
    }

    public function persist(EntityManagerInterface $entityManager,
                            Dashboard\Component    $component): void {

        $config = $component->getConfig();

        $productionStatusesFilter = $config['productionStatuses'] ?? [];
        $productionTypesFilter = $config['productionTypes'] ?? [];
        $scale = $config['scale'] ?? DashboardService::DEFAULT_DAILY_REQUESTS_SCALE;
        $period = $config['period'] ?? DashboardService::DAILY_PERIOD_PREVIOUS_DAYS;
        $date = $config['date'] ?? 'creationDate';
        $separateType = isset($config['separateType']) && $config['separateType'];

        $type = match ($date) {
            'validationDate' => "de validation",
            'treatmentDate' => "de traitement",
            default => "de création", // 'creationDate'
        };

        $hint = "Nombre de demandes de productions ayant leurs dates $type sur les jours présentés";

        $productionRepository = $entityManager->getRepository(ProductionRequest::class);
        $typeRepository = $entityManager->getRepository(Type::class);

        $chartData = $this->dashboardService->getDailyObjectsStatistics(
            $entityManager,
            $scale,
            static fn(DateTime $dateMin, DateTime $dateMax) => $productionRepository->countByDates($dateMin, $dateMax, $separateType, $productionStatusesFilter, $productionTypesFilter, $date),
            $period
        );

        if ($separateType) {
            $types = $typeRepository->findBy(['id' => $productionTypesFilter]);
            $chartData = Stream::from($chartData)
                ->reduce(static function ($carry, $data, $date) use ($types) {
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
