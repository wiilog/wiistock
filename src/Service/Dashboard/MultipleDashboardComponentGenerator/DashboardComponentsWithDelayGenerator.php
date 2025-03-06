<?php

namespace App\Service\Dashboard\MultipleDashboardComponentGenerator;

use App\Entity\Dashboard;
use App\Entity\Dashboard\Meter as DashboardMeter;
use App\Entity\Emplacement;
use App\Entity\Nature;
use App\Entity\Tracking\TrackingDelay;
use App\Service\Dashboard\DashboardService;
use App\Service\FormatService;
use App\Service\Tracking\PackService;
use Doctrine\ORM\EntityManagerInterface;
use WiiCommon\Helper\Stream;

class DashboardComponentsWithDelayGenerator extends MultipleDashboardComponentGenerator {

    public function __construct(private PackService      $packService,
                                private DashboardService $dashboardService,
                                private FormatService    $formatService) {}

    /**
     * @param array<Dashboard\Component> $components
     */
    public function persistAll(EntityManagerInterface $entityManager, array $components): void {
        $trackingDelayRepository = $entityManager->getRepository(TrackingDelay::class);
        $natureRepository = $entityManager->getRepository(Nature::class);
        $locationRepository = $entityManager->getRepository(Emplacement::class);

        $config = $components[0]->getConfig();
        $natures = $config['natures'] ?? [];
        $locations = $config['locations'] ?? [];
        $eventType = $config['treatmentDelayType'] ?? null;

        $naturesFilter = !empty($natures)
            ? $natureRepository->findBy(['id' => $natures])
            : [];

        $locationsFilter = !empty($locations)
            ? $locationRepository->findBy(['id' => $locations])
            : [];

        $eventTypes = DashboardService::TRACKING_EVENT_TO_TREATMENT_DELAY_TYPE[$eventType];
        $trackingDelayByFilters = $trackingDelayRepository->iterateTrackingDelayByFilters($naturesFilter, $locationsFilter, $eventTypes, 1000);

        $trackingDelayLessThan = isset($config['trackingDelayLessThan'])
            ? $config['trackingDelayLessThan'] * 60
            : null;

        $segments = $config['segments'] ?? [];

        $globalCounter = 0;
        $alreadySavedGroups = [];

        $countByNatureBase = Stream::from($naturesFilter)
            ->keymap(fn(Nature $nature) => [
                $this->formatService->nature($nature),
                0,
            ])
            ->toArray();


        if(count($components) > 1){
            foreach($components as $component) {

                if(!$trackingDelayLessThan) {
                    $trackingDelayLessThan = isset($component->getConfig()['trackingDelayLessThan'])
                        ? $component->getConfig()['trackingDelayLessThan'] * 60
                        : null;
                }

                if(empty($segments)) {
                    $segments = $component->getConfig()['segments'] ?? [];
                }
            }
        }

        $customSegments = !empty($segments)
            ? array_merge([-1, 1], $segments)
            : [];
        $counterByEndingSpan = Stream::from($customSegments)
            ->keymap(fn(string $segmentEnd) => [$segmentEnd, $countByNatureBase])
            ->toArray();

        $nextElementToDisplay = null;

        $treatedGroups = [];

        foreach ($trackingDelayByFilters as $trackingDelay) {
            $pack = $trackingDelay->getPack();
            $group = $pack->getGroup();
            $remainingTimeInSeconds = $this->packService->getTrackingDelayRemainingTime($pack);

            if ($trackingDelayLessThan && $remainingTimeInSeconds < $trackingDelayLessThan) {
                // count group only one time if pack is in a group.
                if ($group) {
                    $groupCode = $group->getCode();
                    $alreadySavedGroup = $alreadySavedGroups[$groupCode] ?? false;
                    if (!$alreadySavedGroup) {
                        break;
                    }
                    $alreadySavedGroups[$groupCode] = true;
                }
            } else {
                if ($group) {
                    $groupId = $group->getId();
                    $oldRemainingTime = $treatedGroups[$groupId]["remainingTimeInSeconds"] ?? null;
                    if (!isset($oldRemainingTime) || $remainingTimeInSeconds < $oldRemainingTime) {
                        $treatedGroups[$group->getId()] = [
                            "group" => $group,
                            "pack" => $pack,
                            "remainingTimeInSeconds" => $remainingTimeInSeconds,
                        ];
                    }

                    // We increment counter for group in next foreach
                    // to do not count two times a same group
                    break;
                }
            }

            if(!empty($segments)) {
                $this->dashboardService->treatPack(
                    $pack,
                    $remainingTimeInSeconds,
                    $customSegments,
                    $counterByEndingSpan,
                    $globalCounter,
                    $nextElementToDisplay
                );
            } else {
                $globalCounter++;
            }
        }

        foreach ($treatedGroups as $group) {
            $group = $group['group'];
            $pack = $group['pack'];
            $remainingTimeInSeconds = $group['remainingTimeInSeconds'];

            $this->dashboardService->treatPack(
                $group,
                $remainingTimeInSeconds,
                $customSegments,
                $counterByEndingSpan,
                $globalCounter,
                $nextElementToDisplay,
                $pack
            );
        }


        foreach ($components as $component) {
            $config = $component->getConfig();
            $componentType = $component->getType();
            $meterKey = $componentType->getMeterKey();

            switch ($meterKey) {
                case Dashboard\ComponentType::ONGOING_PACKS_WITH_TRACKING_DELAY:
                    $subtitle = $this->formatService->locations($locationsFilter);

                    $meter = $this->dashboardService->persistDashboardMeter($entityManager, $component, DashboardMeter\Indicator::class);
                    $meter
                        ->setCount($globalCounter ?? 0)
                        ->setSubtitle($subtitle ?? null);
                    break;
                case Dashboard\ComponentType::ENTRIES_TO_HANDLE_BY_TRACKING_DELAY:
                    $graphData = $this->dashboardService->getObjectForTimeSpan(
                        $segments,
                        static fn (int $beginSpan, int $endSpan) => $counterByEndingSpan[$endSpan] ?? [],
                        $component->getType()->getMeterKey()
                    );
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

                    break;
                default:
                    break;
            }
        }
    }
}
