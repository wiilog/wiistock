<?php

namespace App\Service\Dashboard\DashboardComponentGenerator;

use App\Entity\Dashboard;
use App\Entity\Dashboard\Meter as DashboardMeter;
use App\Entity\Emplacement;
use App\Entity\Nature;
use App\Entity\Tracking\Pack;
use App\Entity\Tracking\TrackingDelay;
use App\Service\Dashboard\DashboardService;
use App\Service\FormatService;
use App\Service\PackService;
use Doctrine\ORM\EntityManagerInterface;
use WiiCommon\Helper\Stream;

class EntriesToHandleByTrackingDelayComponentGenerator implements DashboardComponentGenerator {

    public function __construct(
        private PackService $packService,
        private FormatService $formatService,
        private DashboardService $dashboardService,
    ) {
    }

    public function persist(EntityManagerInterface $entityManager,
                            Dashboard\Component    $component): void {

        $config = $component->getConfig();
        $natureRepository = $entityManager->getRepository(Nature::class);
        $locationRepository = $entityManager->getRepository(Emplacement::class);
        $trackingDelayRepository = $entityManager->getRepository(TrackingDelay::class);

        $naturesFilter = !empty($config['natures'])
            ? $natureRepository->findBy(['id' => $config['natures']])
            : [];

        $locationsFilter = !empty($config['locations'])
            ? $locationRepository->findBy(['id' => $config['locations']])
            : [];

        $maxResultPack = 1000;
        $globalCounter = 0;

        if (!empty($naturesFilter) && !empty($locationsFilter)) {
            $eventTypes = DashboardService::TRACKING_EVENT_TO_TREATMENT_DELAY_TYPE[$config['treatmentDelayType']];
            $trackingDelayByFilters = $trackingDelayRepository->iterateTrackingDelayByFilters($naturesFilter, $locationsFilter, $eventTypes, $maxResultPack);

            $countByNatureBase = Stream::from($naturesFilter)
                ->keymap(fn(Nature $nature) => [
                    $this->formatService->nature($nature),
                    0,
                ])
                ->toArray();

            $segments = $config['segments'];

            $customSegments = array_merge([-1, 1], $segments);
            $counterByEndingSpan = Stream::from($customSegments)
                ->keymap(fn(string $segmentEnd) => [$segmentEnd, $countByNatureBase])
                ->toArray();
            $nextElementToDisplay = null;

            $treatedGroups = [];

            foreach($trackingDelayByFilters as $trackingDelay){
                $pack = $trackingDelay->getPack();
                $group = $pack->getGroup();

                $remainingTimeInSeconds = $this->packService->getTrackingDelayRemainingTime($pack);

                if ($group) {
                    $groupId = $group->getId();
                    $oldRemainingTime = $treatedGroups[$groupId]["remainingTimeInSeconds"] ?? null;
                    if (!isset($oldRemainingTime) || $remainingTimeInSeconds < $oldRemainingTime) {
                        $treatedGroups[$groupId] = [
                            "group" => $group,
                            "pack" => $pack,
                            "remainingTimeInSeconds" => $remainingTimeInSeconds,
                        ];
                    }

                    // We increment counter for group in next foreach
                    // to do not count two times a same group
                    break;
                }

                $this->treatPack(
                    $pack,
                    $remainingTimeInSeconds,
                    $customSegments,
                    $counterByEndingSpan,
                    $globalCounter,
                    $nextElementToDisplay
                );
            }

            foreach ($treatedGroups as $groupArray) {
                $group = $groupArray['group'];
                $pack = $groupArray['pack'];
                $remainingTimeInSeconds = $groupArray['remainingTimeInSeconds'];

                $this->treatPack(
                    $group,
                    $remainingTimeInSeconds,
                    $customSegments,
                    $counterByEndingSpan,
                    $globalCounter,
                    $nextElementToDisplay,
                    $pack
                );
            }

            $graphData = $this->dashboardService->getObjectForTimeSpan(
                $segments,
                static fn (int $beginSpan, int $endSpan) => $counterByEndingSpan[$endSpan] ?? [],
                $component->getType()->getMeterKey()
            );
        }

        if (empty($graphData)) {
            $graphData = $this->dashboardService->getObjectForTimeSpan([], static fn() => 0, $component->getType()->getMeterKey());
        }

        // sum of counters > 1, at least one pack
        if (isset($nextElementToDisplay)) {
            $packToDisplay = $nextElementToDisplay['pack'] ?? null;

            $nextElementIdToDisplay = $packToDisplay?->getId();
            $config['nextElement'] = $nextElementIdToDisplay;

            $locationToDisplay = $packToDisplay?->getLastOngoingDrop()?->getEmplacement() ?? null;
        }
        else {
            $config['nextElement'] = null;
            $packToDisplay = null;
            $locationToDisplay = null;
        }

        $component->setConfig($config);

        $totalToDisplay = $globalCounter ?: null;
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
            ->setNextElement($packToDisplay?->getCode() ?: '-')
            ->setLocation($locationToDisplay ?: '-');
    }


    private function treatPack(Pack   $pack,
                               int    $remainingTimeInSeconds,
                               array  $customSegments,
                               array  &$counterByEndingSpan,
                               int    &$globalCounter,
                               ?array &$nextElementToDisplay,
                               ?Pack  $packToGetNature = null): void {
        // we save pack with the smallest tracking delay
        if (!isset($nextElementToDisplay)
            || ($remainingTimeInSeconds < $nextElementToDisplay['remainingTimeInSeconds'])) {
            $nextElementToDisplay = [
                'remainingTimeInSeconds' => $remainingTimeInSeconds,
                'pack' => $pack,
            ];
        }

        foreach ($customSegments as $segmentEnd) {
            $endSpan = match($segmentEnd) {
                -1 => -1,
                default => $segmentEnd * 60,
            };

            if ($remainingTimeInSeconds < $endSpan) {
                $packToGetNature ??= $pack;
                $natureLabel = $this->formatService->nature($packToGetNature->getNature());

                $counterByEndingSpan[$segmentEnd][$natureLabel] ??= 0;
                $counterByEndingSpan[$segmentEnd][$natureLabel]++;
                $globalCounter++;

                break;
            }
        }
    }

}
