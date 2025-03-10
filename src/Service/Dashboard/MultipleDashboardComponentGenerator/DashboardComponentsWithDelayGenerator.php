<?php

namespace App\Service\Dashboard\MultipleDashboardComponentGenerator;

use App\Entity\Dashboard;
use App\Entity\Dashboard\Meter as DashboardMeter;
use App\Entity\Emplacement;
use App\Entity\Nature;
use App\Entity\Tracking\Pack;
use App\Entity\Tracking\TrackingDelay;
use App\Service\Dashboard\DashboardService;
use App\Service\FormatService;
use App\Service\Tracking\PackService;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use WiiCommon\Helper\Stream;

class DashboardComponentsWithDelayGenerator extends MultipleDashboardComponentGenerator {

    private const MAX_TRACKING_DELAY_TO_TREAT = 1000;

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
        $trackingDelayByFilters = $trackingDelayRepository->iterateTrackingDelayByFilters($naturesFilter, $locationsFilter, $eventTypes, self::MAX_TRACKING_DELAY_TO_TREAT);

        $countByNatureBase = Stream::from($naturesFilter)
            ->keymap(fn(Nature $nature) => [
                $this->formatService->nature($nature),
                0,
            ])
            ->toArray();

        $componentsData = Stream::from($components)
            ->keymap(fn(Dashboard\Component $component) => [
                $component->getId(),
                $this->getInitialComponentData($component, $countByNatureBase)
            ])
            ->toArray();

        $treatedGroups = [];

        foreach ($trackingDelayByFilters as $trackingDelay) {
            $pack = $trackingDelay->getPack();
            $group = $pack->getGroup();
            $remainingTimeInSeconds = $this->packService->getTrackingDelayRemainingTime($pack);

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
                continue;
            }

            $this->treatPack($componentsData, $pack, $remainingTimeInSeconds);
        }

        foreach ($treatedGroups as $group) {
            $group = $group['group'];
            $pack = $group['pack'];
            $remainingTimeInSeconds = $group['remainingTimeInSeconds'];

            $this->treatPack($componentsData, $group, $remainingTimeInSeconds, $pack);
        }

        $this->persistComponents($entityManager, $componentsData, $naturesFilter, $locationsFilter);
    }

    /**
     * @param array<Emplacement> $locationsFilter
     * @param array<Nature> $naturesFilter
     */
    private function persistComponents(EntityManagerInterface $entityManager,
                                       array                  $componentsData,
                                       array                  $naturesFilter,
                                       array                  $locationsFilter): void {
        foreach ($componentsData as $componentData) {
            $component = $componentData['component'];
            $meterKey = $componentData["meterKey"] ?? null;

            switch ($meterKey) {
                case Dashboard\ComponentType::ONGOING_PACKS_WITH_TRACKING_DELAY:
                    $this->persistIndicator($entityManager, $component, $componentData, $locationsFilter);
                    break;
                case Dashboard\ComponentType::ENTRIES_TO_HANDLE_BY_TRACKING_DELAY:
                    $this->persistChart($entityManager, $component, $componentData, $naturesFilter);
                    break;
                default:
                    throw new Exception('Invalid meter key');
            }
        }
    }

    /**
     * @param array<Emplacement> $locationsFilter
     */
    private function persistIndicator(EntityManagerInterface $entityManager,
                                      Dashboard\Component    $component,
                                      array                  $componentData,
                                      array                  $locationsFilter): void {
        $globalCounter = $componentData["globalCounter"] ?? null;
        $subtitle = $this->formatService->locations($locationsFilter);

        $meter = $this->dashboardService->persistDashboardMeter($entityManager, $component, DashboardMeter\Indicator::class);
        $meter
            ->setCount($globalCounter ?: 0)
            ->setSubtitle($subtitle ?? null);
    }

    /**
     * @param array<Nature> $naturesFilter
     */
    private function persistChart(EntityManagerInterface $entityManager,
                                  Dashboard\Component    $component,
                                  array                  $componentData,
                                  array                  $naturesFilter): void {

        $segments = $componentData['segments'] ?? [];
        $counterByEndingSpan =  $componentData['counterByEndingSpan'] ?? [];
        $globalCounter =  $componentData['globalCounter'] ?? [];

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
    }

    private function treatPack(array  &$componentsData,
                               Pack   $pack,
                               int    $remainingTimeInSeconds,
                               ?Pack  $packToGetNature = null): void {

        foreach ($componentsData as &$componentData) {
            $meterKey = $componentData['meterKey'] ?? null;
            switch ($meterKey) {
                case Dashboard\ComponentType::ONGOING_PACKS_WITH_TRACKING_DELAY:
                    $trackingDelayLessThan = $componentData['trackingDelayLessThan'] ?? null;
                    if ($remainingTimeInSeconds < $trackingDelayLessThan) {
                        $componentData["globalCounter"]++;
                    }

                    break;
                case Dashboard\ComponentType::ENTRIES_TO_HANDLE_BY_TRACKING_DELAY:
                    $nextElementToDisplay = $componentData["nextElementToDisplay"] ?? null;
                    $segments = $componentData["segments"] ?? [];
                    $customSegments = $componentData["customSegments"] ?? [];
                    $counterByEndingSpan = $componentData["counterByEndingSpan"] ?? [];

                    if (empty($segments)) {
                        // if segment config empty for this component we only increment the global counter
                        $componentData["globalCounter"]++;
                        break; // we break switch and continue foreach
                    }

                    // we save pack with the smallest tracking delay
                    if (!isset($nextElementToDisplay)
                        || ($remainingTimeInSeconds < $nextElementToDisplay['remainingTimeInSeconds'])) {
                        $componentData["nextElementToDisplay"] = [
                            'remainingTimeInSeconds' => $remainingTimeInSeconds,
                            'pack' => $pack,
                        ];
                    }

                    // increment right counter according to the pack remainingTime
                    foreach ($customSegments as $segmentEnd) {
                        $endSpan = match ($segmentEnd) {
                            -1 => -1,
                            default => $segmentEnd * 60,
                        };

                        if ($remainingTimeInSeconds < $endSpan) {
                            $packToGetNature ??= $pack;
                            $natureLabel = $this->formatService->nature($packToGetNature->getNature());

                            $counterByEndingSpan[$segmentEnd][$natureLabel] ??= 0;
                            $counterByEndingSpan[$segmentEnd][$natureLabel]++;
                            $componentData["globalCounter"]++;

                            break;
                        }
                    }
                    break;
                default:
                    throw new Exception('Invalid meter key');
            }

        }
    }

    /**
     * @param array<string, int> $countByNatureBase
     */
    private function getInitialComponentData(Dashboard\Component $component,
                                             array               $countByNatureBase): array {

        $config = $component->getConfig();
        $meterKey = $component->getType()->getMeterKey();

        switch ($component->getType()->getMeterKey()) {
            case Dashboard\ComponentType::ONGOING_PACKS_WITH_TRACKING_DELAY:
                return [
                    "meterKey" => $meterKey,
                    "component" => $component,
                    "globalCounter" => 0,
                    "trackingDelayLessThan" => isset($config['trackingDelayLessThan'])
                        ? $config['trackingDelayLessThan'] * 60
                        : null,
                ];
            case Dashboard\ComponentType::ENTRIES_TO_HANDLE_BY_TRACKING_DELAY:
                $segments = $config['segments'] ?? [];
                $customSegments = !empty($config['segments'] ?? [])
                    ? array_merge([-1, 1], $segments)
                    : [];

                return [
                    "meterKey" => $meterKey,
                    "component" => $component,
                    "segments" => $segments,
                    "customSegments" => $customSegments,
                    "counterByEndingSpan" => Stream::from($customSegments)
                        ->keymap(fn(string $segmentEnd) => [$segmentEnd, $countByNatureBase])
                        ->toArray(),
                    "nextElementToDisplay" => null,
                    "globalCounter" => 0,
                ];
            default:
                throw new Exception('Invalid meter key');
        }
    }
}
