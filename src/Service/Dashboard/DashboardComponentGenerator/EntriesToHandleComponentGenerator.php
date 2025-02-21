<?php

namespace App\Service\Dashboard\DashboardComponentGenerator;

use App\Entity\Dashboard;
use App\Entity\Dashboard\Meter as DashboardMeter;
use App\Entity\Language;
use App\Entity\LocationCluster;
use App\Entity\Nature;
use App\Exceptions\DashboardException;
use App\Helper\LanguageHelper;
use App\Service\Dashboard\DashboardService;
use App\Service\DateTimeService;
use App\Service\EnCoursService;
use App\Service\FormatService;
use App\Service\LanguageService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use WiiCommon\Helper\Stream;

class EntriesToHandleComponentGenerator implements DashboardComponentGenerator {

    public function __construct(
        private LanguageService $languageService,
        private DateTimeService $dateTimeService,
        private FormatService $formatService,
        private DashboardService $dashboardService,
        private EnCoursService $enCoursService,
    ) {
    }

    public function persist(EntityManagerInterface $entityManager,
                            Dashboard\Component    $component): void {


        $config = $component->getConfig();
        $natureRepository = $entityManager->getRepository(Nature::class);
        $locationClusterRepository = $entityManager->getRepository(LocationCluster::class);

        $naturesFilter = !empty($config['natures'])
            ? $natureRepository->findBy(['id' => $config['natures']])
            : [];

        $clusterKey = 'locations';
        $this->dashboardService->updateComponentLocationCluster($entityManager, $component, $clusterKey);
        $entityManager->flush();

        $locationCluster = $component->getLocationCluster($clusterKey);

        $locationCounters = [];

        $globalCounter = 0;

        $maxResultPackOnCluster = 1000;

        $olderPackLocation = [
            'locationLabel' => null,
            'locationId' => null,
            'packDateTime' => null
        ];

        if (!empty($naturesFilter)) {
            $defaultSlug = LanguageHelper::clearLanguage($this->languageService->getDefaultSlug($entityManager));
            $defaultLanguage = $entityManager->getRepository(Language::class)->findOneBy(['slug' => $defaultSlug]);
            $nbPacksOnCluster = $locationClusterRepository->countPacksOnCluster($locationCluster, $naturesFilter, $defaultLanguage);
            if ($nbPacksOnCluster > $maxResultPackOnCluster) {
                throw new DashboardException("Nombre de données trop important");
            }
            $packsOnCluster = $locationClusterRepository->getPacksOnCluster($locationCluster, $naturesFilter, $defaultLanguage);

            $countByNatureBase = [];
            foreach ($naturesFilter as $wantedNature) {
                $countByNatureBase[$this->formatService->nature($wantedNature)] = 0;
            }
            $segments = $config['segments'];

            $lastSegmentKey = count($segments) - 1;
            $adminDelay = "$segments[$lastSegmentKey]:00";

            $truckArrivalTime = $config['truckArrivalTime'] ?? null;

            $graphData = $this->dashboardService->getObjectForTimeSpan($segments, function (int $beginSpan, int $endSpan)
            use (
                $entityManager,
                $countByNatureBase,
                &$packsOnCluster,
                $adminDelay,
                &$locationCounters,
                &$olderPackLocation,
                &$globalCounter,
                $truckArrivalTime) {
                $countByNature = array_merge($countByNatureBase);
                $packUntreated = [];
                foreach ($packsOnCluster as $pack) {
                    $interval = $this->dateTimeService->getWorkedPeriodBetweenDates($entityManager, $pack['firstTrackingDateTime'], new DateTime("now"));
                    $timeInformation = $this->enCoursService->getTimeInformation($interval, $adminDelay);
                    $countDownHours = isset($timeInformation['countDownLateTimespan'])
                        ? ($timeInformation['countDownLateTimespan'] / 1000 / 60 / 60)
                        : null;

                    $countDownHours -= $truckArrivalTime && $pack['truckArrivalDelay']
                        ? intval($pack['truckArrivalDelay']) / 1000 / 60 / 60
                        : 0;

                    if (isset($countDownHours)
                        && (
                            ($countDownHours < 0 && $beginSpan === -1) // count late pack
                            || ($countDownHours >= 0 && $countDownHours >= $beginSpan && $countDownHours < $endSpan)
                        )) {

                        $this->updateOlderPackLocation($olderPackLocation, $pack);

                        $natureLabel = $pack['natureLabel'];
                        $countByNature[$natureLabel] = $countByNature[$natureLabel] ?? 0;
                        $countByNature[$natureLabel]++;

                        $currentLocationId = $pack['currentLocationId'];
                        $locationCounters[$currentLocationId] = $locationCounters[$currentLocationId] ?? 0;
                        $locationCounters[$currentLocationId]++;

                        $globalCounter++;
                    } else {
                        $packUntreated[] = $pack;
                    }
                }

                $packsOnCluster = $packUntreated;

                return $countByNature;
            }, $component->getType()->getMeterKey());
        }

        if (empty($graphData)) {
            $graphData = $this->dashboardService->getObjectForTimeSpan([], static fn() => 0, $component->getType()->getMeterKey());
        }

        $totalToDisplay = $olderPackLocation['locationId'] ? $globalCounter : null;
        $locationToDisplay = $olderPackLocation['locationLabel'] ?: null;
        $chartColors = Stream::from($naturesFilter)
            ->filter(fn (Nature $nature) => $nature->getColor())
            ->keymap(fn(Nature $nature) => [
                $this->formatService->nature($nature),
                $nature->getColor()
            ])
            ->toArray();

        $meter = $this->dashboardService->persistDashboardMeter($entityManager, $component, DashboardMeter\Chart::class);

        $meter
            ->setChartColors($chartColors)
            ->setData($graphData)
            ->setTotal($totalToDisplay ?: '-')
            ->setLocation($locationToDisplay ?: '-');
    }

    private function updateOlderPackLocation(array &$olderPackLocation, array $pack): void
    {
        if (empty($olderPackLocation['locationLabel'])
            || empty($olderPackLocation['locationId'])
            || empty($olderPackLocation['packDateTime'])
            || $olderPackLocation['packDateTime'] > $pack['lastTrackingDateTime'])
        {
            $olderPackLocation['locationLabel'] = $pack['currentLocationLabel'];
            $olderPackLocation['locationId'] = $pack['currentLocationId'];
            $olderPackLocation['packDateTime'] = $pack['lastTrackingDateTime'];
        }
    }

}
