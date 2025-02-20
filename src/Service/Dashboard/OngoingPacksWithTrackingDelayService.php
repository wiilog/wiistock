<?php

namespace App\Service\Dashboard;

use App\Entity\Emplacement;
use App\Entity\Nature;
use App\Entity\Tracking\TrackingDelay;
use App\Service\FormatService;
use App\Service\PackService;
use Doctrine\ORM\EntityManagerInterface;
use WiiCommon\Helper\Stream;
use App\Entity\Dashboard;
use App\Entity\Dashboard\Meter as DashboardMeter;

class OngoingPacksWithTrackingDelayService implements DashboardComponentService {

    public function __construct(
        private PackService $packService,
        private FormatService $formatService,
        private DashboardService $dashboardService,
    ) {
    }

    public function persist(EntityManagerInterface $entityManager,
                            Dashboard\Component    $component,
                            array                  $options = []): void {
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

            $globalCounter = Stream::from($trackingDelayByFilters)
                ->filter(function (TrackingDelay $trackingDelay) use ($trackingDelayLessThan) {
                    $pack = $trackingDelay->getPack();
                    $remainingTimeInSeconds = $this->packService->getTrackingDelayRemainingTime($pack);

                    return $remainingTimeInSeconds < $trackingDelayLessThan;
                })
                ->count();
            $subtitle = $this->formatService->locations($locationsFilter);
        }

        $meter = $this->dashboardService->persistDashboardMeter($entityManager, $component, DashboardMeter\Indicator::class);

        $meter
            ->setCount($globalCounter ?? 0)
            ->setSubtitle($subtitle ?? null);
    }
}
