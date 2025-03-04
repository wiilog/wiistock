<?php

namespace App\Service\Dashboard\DashboardComponentGenerator;

use App\Entity\Dashboard;
use App\Entity\Dashboard\Meter as DashboardMeter;
use App\Entity\Emplacement;
use App\Entity\Tracking\Pack;
use App\Service\Dashboard\DashboardService;
use App\Service\DateTimeService;
use App\Service\EnCoursService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;

class OngoingPackComponentGenerator implements DashboardComponentGenerator {

    public function __construct(
        private DashboardService $dashboardService,
        private DateTimeService  $dateTimeService,
        private EnCoursService   $enCoursService,
    ) {
    }

    public function persist(EntityManagerInterface $entityManager,
                            Dashboard\Component    $component): void {
        $config = $component->getConfig();

        $calculatedData = $this->getDashboardCounter(
            $entityManager,
            $config['locations'],
            (bool)$config['withTreatmentDelay'],
            (bool)$config['withLocationLabels']
        );

        $meter = $this->dashboardService->persistDashboardMeter($entityManager, $component, DashboardMeter\Indicator::class);

        $meter
            ->setCount($calculatedData ? $calculatedData['count'] : 0)
            ->setDelay($calculatedData ? $calculatedData['delay'] : 0)
            ->setSubtitle($calculatedData['subtitle'] ?? null);
    }

    private function getDashboardCounter(EntityManagerInterface $entityManager,
                                        array $locationIds,
                                        bool $includeDelay = false,
                                        bool $includeLocationLabels = false): ?array {
        $packRepository = $entityManager->getRepository(Pack::class);

        if (!empty($locationIds)) {
            $locationRepository = $entityManager->getRepository(Emplacement::class);
            $locations = $locationRepository->findBy(['id' => $locationIds]);
        } else {
            $locations = [];
        }

        if (!empty($locations)) {
            $response = [];
            $response['delay'] = null;
            if ($includeDelay) {
                $lastEnCours = $packRepository->getCurrentPackOnLocations(
                    $locationIds,
                    [
                        'isCount' => false,
                        'field' => 'lastOngoingDrop.datetime, emplacement.dateMaxTime',
                        'limit' => 1,
                        'onlyLate' => true,
                        'order' => 'asc'
                    ]
                );
                if (!empty($lastEnCours[0]) && $lastEnCours[0]['dateMaxTime']) {
                    $lastEnCoursDateTime = $lastEnCours[0]['datetime'];
                    $date = $this->dateTimeService->getWorkedPeriodBetweenDates($entityManager, $lastEnCoursDateTime, new DateTime("now"));
                    $timeInformation = $this->enCoursService->getTimeInformation($date, $lastEnCours[0]['dateMaxTime']);
                    $response['delay'] = $timeInformation['countDownLateTimespan'];
                }
            }
            $response['subtitle'] = $includeLocationLabels
                ? array_reduce(
                    $locations,
                    function(string $carry, Emplacement $location) {
                        return $carry . (!empty($carry) ? ', ' : '') . $location->getLabel();
                    },
                    ''
                )
                : null;
            $response['count'] = $packRepository->getCurrentPackOnLocations($locationIds);
        } else {
            $response = null;
        }

        return $response;
    }
}
