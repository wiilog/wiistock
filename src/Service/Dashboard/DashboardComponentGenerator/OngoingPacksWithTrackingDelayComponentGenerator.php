<?php

namespace App\Service\Dashboard\DashboardComponentGenerator;

use App\Entity\Dashboard;
use App\Entity\Dashboard\Meter as DashboardMeter;
use App\Entity\Emplacement;
use App\Entity\Nature;
use App\Entity\Tracking\TrackingDelay;
use App\Service\Dashboard\DashboardService;
use App\Service\FormatService;
use App\Service\Tracking\PackService;
use Doctrine\ORM\EntityManagerInterface;

class OngoingPacksWithTrackingDelayComponentGenerator implements DashboardComponentGenerator {

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

        $maxResultPack = 1000;
        $naturesFilter = !empty($config['natures'])
            ? $natureRepository->findBy(['id' => $config['natures']])
            : [];

        $locationsFilter = !empty($config['locations'])
            ? $locationRepository->findBy(['id' => $config['locations']])
            : [];

        if (!empty($naturesFilter) && !empty($locationsFilter)){
            $trackingDelayLessThan = $config['trackingDelayLessThan'] * 60;
            $eventTypes = DashboardService::TRACKING_EVENT_TO_TREATMENT_DELAY_TYPE[$config['treatmentDelayType']];
            $trackingDelayByFilters = $trackingDelayRepository->iterateTrackingDelayByFilters($naturesFilter, $locationsFilter, $eventTypes, $maxResultPack);

            $globalCounter = 0;
            $alreadySavedGroups = [];

            // This is not a stream to don't call iterator_to_array and don't consume the iterator
            foreach ($trackingDelayByFilters as $trackingDelay) {
                $pack = $trackingDelay->getPack();
                $remainingTimeInSeconds = $this->packService->getTrackingDelayRemainingTime($pack);

                if ($remainingTimeInSeconds < $trackingDelayLessThan) {
                    $group = $pack->getGroup();

                    // count group only one time if pack is in a group.
                    if ($group) {
                        $groupCode = $group->getCode();
                        $alreadySavedGroup = $alreadySavedGroups[$groupCode] ?? false;
                        if (!$alreadySavedGroup) {
                            break;
                        }
                        $alreadySavedGroups[$groupCode] = true;
                    }

                    $globalCounter++;
                }
            }
            $subtitle = $this->formatService->locations($locationsFilter);
        }

        $meter = $this->dashboardService->persistDashboardMeter($entityManager, $component, DashboardMeter\Indicator::class);

        $meter
            ->setCount($globalCounter ?? 0)
            ->setSubtitle($subtitle ?? null);
    }
}
