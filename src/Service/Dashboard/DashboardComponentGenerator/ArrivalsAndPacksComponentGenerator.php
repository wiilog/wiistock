<?php

namespace App\Service\Dashboard\DashboardComponentGenerator;

use App\Entity\Arrivage;
use App\Entity\Dashboard;
use App\Entity\Dashboard\Meter as DashboardMeter;
use App\Entity\Nature;
use App\Entity\Tracking\Pack;
use App\Service\Dashboard\DashboardService;
use App\Service\FormatService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use WiiCommon\Helper\Stream;

class ArrivalsAndPacksComponentGenerator implements DashboardComponentGenerator {

    public function __construct(
        private DashboardService $dashboardService,
        private FormatService    $formatService,
    ) {
    }

    public function persist(EntityManagerInterface $entityManager,
                            Dashboard\Component    $component): void {
        $config = $component->getConfig();
        $type = $component->getType();
        $weeklyRequest = ($type->getMeterKey() === Dashboard\ComponentType::WEEKLY_ARRIVALS_AND_PACKS);
        $dailyRequest = ($type->getMeterKey() === Dashboard\ComponentType::DAILY_ARRIVALS_AND_PACKS);

        if (!$dailyRequest && !$weeklyRequest) {
            throw new InvalidArgumentException('Invalid component type');
        }

        $displayPackNatures = $config['displayPackNatures'] ?? false;
        $arrivalStatusesFilter = $config['arrivalStatuses'] ?? [];
        $arrivalTypesFilter = $config['arrivalTypes'] ?? [];
        if ($dailyRequest) {
            $scale = $config['daysNumber'] ?? DashboardService::DEFAULT_DAILY_REQUESTS_SCALE;
        } else {
            $scale = DashboardService::DEFAULT_WEEKLY_REQUESTS_SCALE;
        }

        $arrivageRepository = $entityManager->getRepository(Arrivage::class);

        if ($dailyRequest) {
            $getObjectsStatisticsCallable = 'getDailyObjectsStatistics';
        } else {
            $getObjectsStatisticsCallable = 'getWeeklyObjectsStatistics';
        }

        // arrivals column
        $chartData = $this->dashboardService->{$getObjectsStatisticsCallable}(
            $entityManager,
            $scale,
            function(DateTime $dateMin, DateTime $dateMax) use ($arrivageRepository, $arrivalStatusesFilter, $arrivalTypesFilter) {
                return $arrivageRepository->countByDates($dateMin, $dateMax, $arrivalStatusesFilter, $arrivalTypesFilter);
            }
        );
        // packs column
        if ($scale) {
            $natureData = $this->getArrivalPacksData($entityManager, $getObjectsStatisticsCallable, $scale, $arrivalStatusesFilter, $arrivalTypesFilter, $displayPackNatures);

            if ($natureData) {
                $chartData['stack'] = $natureData;
            }
            if(!$displayPackNatures && isset($config['chartColor1'])) {
                $chartData['stack'][0]['backgroundColor'] = $config['chartColor1'];
            }
        }
        $meter = $this->dashboardService->persistDashboardMeter($entityManager, $component, DashboardMeter\Chart::class);
        $meter
            ->setData($chartData);
    }

    private function getArrivalPacksData(EntityManagerInterface $entityManager,
                                         string $getObjectsStatisticsCallable,
                                         int $scale,
                                         array $arrivalStatusesFilter,
                                         array $arrivalTypesFilter,
                                         bool $displayPackNatures = false): array {

        $packRepository = $entityManager->getRepository(Pack::class);
        $natureRepository = $entityManager->getRepository(Nature::class);

        $packCountByDay = $this->{$getObjectsStatisticsCallable}(
            $entityManager,
            $scale,
            function(DateTime $dateMin, DateTime $dateMax) use ($packRepository, $arrivalStatusesFilter, $arrivalTypesFilter, $displayPackNatures) {
                return $packRepository->countPacksByDates($dateMin, $dateMax, $displayPackNatures, $arrivalStatusesFilter, $arrivalTypesFilter);
            }
        );
        $naturesStack = [];
        if ($displayPackNatures) {
            $natures = $natureRepository->findAll();
            foreach ($natures as $nature) {
                $natureId = $nature->getId();
                if (!isset($naturesStack[$natureId])) {
                    $naturesStack[$natureId] = [
                        'id' => $natureId,
                        'label' => $this->formatService->nature($nature),
                        'backgroundColor' => $nature->getColor(),
                        'stack' => 'stack',
                        'data' => []
                    ];
                }
                foreach ($packCountByDay as $countersGroupByNature) {
                    $found = false;
                    if (!empty($countersGroupByNature)) {
                        foreach ($countersGroupByNature as $natureCount) {
                            $currentNatureId = (int)$natureCount['natureId'];
                            if ($natureId === $currentNatureId) {
                                $naturesStack[$natureId]['data'][] = (int)$natureCount['count'];
                                $found = true;
                                break;
                            }
                        }
                    }

                    if (!$found) {
                        $naturesStack[$nature->getId()]['data'][] = 0;
                    }
                }
                $total = Stream::from($naturesStack[$nature->getId()]['data'])
                    ->reduce(function ($counter, $current) {
                        return $counter + $current;
                    }, 0);

                if ($total === 0) {
                    unset($naturesStack[$nature->getId()]);
                }
            }
        } else {
            $naturesStack[] = [
                'label' => 'Unité logistique',
                'backgroundColor' => '#E5E1E1',
                'stack' => 'stack',
                'data' => []
            ];
            foreach ($packCountByDay as $packCount) {
                $naturesStack[0]['data'][] = $packCount;
            }
        }
        return array_values($naturesStack);
    }
}
