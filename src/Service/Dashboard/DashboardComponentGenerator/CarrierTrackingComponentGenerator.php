<?php

namespace App\Service\Dashboard\DashboardComponentGenerator;

use App\Entity\Dashboard;
use App\Entity\Dashboard\Meter as DashboardMeter;
use App\Entity\Emplacement;
use App\Entity\Transporteur;
use App\Entity\TruckArrivalLine;
use App\Service\Dashboard\DashboardService;
use App\Service\FormatService;
use App\Service\TruckArrivalLineService;
use Doctrine\ORM\EntityManagerInterface;
use WiiCommon\Helper\Stream;

class CarrierTrackingComponentGenerator implements DashboardComponentGenerator {

    public function __construct(
        private DashboardService        $dashboardService,
        private TruckArrivalLineService $truckArrivalLineService,
        private FormatService           $formatService,
    ) {
    }

    public function persist(EntityManagerInterface $entityManager,
                            Dashboard\Component    $component): void {
        $config = $component->getConfig();
        $carrierRepository = $entityManager->getRepository(Transporteur::class);
        $locationRepository = $entityManager->getRepository(Emplacement::class);
        $lineRepository = $entityManager->getRepository(TruckArrivalLine::class);
        $carriers = $carrierRepository->getDailyArrivalCarriersLabel($config['carriers'] ?? []);

        $meter = $this->dashboardService->persistDashboardMeter($entityManager, $component, DashboardMeter\Indicator::class);
        $meter->setSubtitle($this->formatService->carriers($carriers) ?: '-');
        $meter->setCount(0);
        if (isset($config['displayUnassociatedLines']) && $config['displayUnassociatedLines']) {
            $locations = $locationRepository->findBy(["id" => $config['locations']]);

            $unassociatedLines = $lineRepository->getUnassociatedLines([
                'locations' => Stream::from($locations)
                    ->map(static fn(Emplacement $location) => $location->getId())
                    ->toArray(),
            ]);
            $meter->setCount(count($unassociatedLines));

            if (isset($config['displayLateLines']) && $config['displayLateLines']) {
                $lateLines = Stream::from($unassociatedLines)
                    ->filter((fn(TruckArrivalLine $line) => $this->truckArrivalLineService->lineIsLate($line, $entityManager)))
                    ->count();
                $meter
                    ->setSubCounts([
                        '<span>Numéros de tracking transporteur non associés</span>',
                        '<span class="text-wii-black">Dont</span> <span class="font-">' . $lateLines . '</span> <span class="text-wii-black">en retard</span>'
                    ]);
            }
        }
    }
}
