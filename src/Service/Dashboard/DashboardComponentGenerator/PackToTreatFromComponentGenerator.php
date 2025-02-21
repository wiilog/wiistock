<?php

namespace App\Service\Dashboard\DashboardComponentGenerator;

use App\Entity\Dashboard;
use App\Entity\Dashboard\Meter as DashboardMeter;
use App\Entity\LocationClusterMeter;
use App\Service\Dashboard\DashboardService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use WiiCommon\Helper\Stream;

class PackToTreatFromComponentGenerator implements DashboardComponentGenerator {

    public function __construct(
        private DashboardService $dashboardService,
    ) {
    }

    public function persist(EntityManagerInterface $entityManager,
                            Dashboard\Component    $component): void {

        $locationClusterMeterRepository = $entityManager->getRepository(LocationClusterMeter::class);

        $config = $component->getConfig();
        $config['legends'] = [];
        $countLegend = 1;
        foreach($config['chartColors'] ?? [] as $key => $legend){
            $config['legends'][$key] = [];
            Stream::from($config)
                ->each(function($conf, $arrayKey) use ($countLegend, $key, &$config) {
                    if (str_starts_with($arrayKey, 'legend') && str_contains($arrayKey, '_') && str_contains($arrayKey, $countLegend)) {
                        $explode = explode('_', $arrayKey);
                        $config['legends'][$key][$explode[1]] = $conf;
                        unset($config[$arrayKey]);
                    }
                });
            $countLegend++;
        }

        $legend1 = 'Legende1';
        $legend2 = 'Legende2';
        $clusterKeys = ['firstOriginLocation', 'secondOriginLocation', 'firstDestinationLocation', 'secondDestinationLocation'];
        foreach ($clusterKeys as $key) {
            $this->dashboardService->updateComponentLocationCluster($entityManager, $component, $key);
        }

        $entityManager->flush();
        $needsFirstOriginFilter = $component->getLocationCluster('firstOriginLocation')
            && $component->getLocationCluster('firstOriginLocation')->getLocations()->count() > 0;
        $data = [
            'chartColors' => [
                $legend1 => $config['chartColors']['Legende1'] ?? null,
                $legend2 => $config['chartColors']['Legende2'] ?? null
            ],
            'chartData' => $this->dashboardService->getDailyObjectsStatistics($entityManager, DashboardService::DEFAULT_WEEKLY_REQUESTS_SCALE, function (DateTime $date) use (
                $legend1,
                $legend2,
                $locationClusterMeterRepository,
                $component,
                $needsFirstOriginFilter
            ) {
                return [
                    $legend1 => $locationClusterMeterRepository->countByDate(
                        $date,
                        $component->getLocationCluster('firstDestinationLocation'),
                        $needsFirstOriginFilter ? $component->getLocationCluster('firstOriginLocation') : null
                    ),
                    $legend2 => $locationClusterMeterRepository->countByDate(
                        $date,
                        $component->getLocationCluster('secondDestinationLocation'),
                        $component->getLocationCluster('secondOriginLocation')
                    )
                ];
            })
        ];
        $meter = $this->dashboardService->persistDashboardMeter($entityManager, $component, DashboardMeter\Chart::class);
        $meter->setData($data['chartData']);
        $meter->setChartColors($data['chartColors']);
    }
}
