<?php

namespace App\Service\Dashboard\DashboardComponentGenerator;

use App\Entity\Dashboard;
use App\Entity\Dashboard\Meter as DashboardMeter;
use App\Entity\Handling;
use App\Service\Dashboard\DashboardService;
use App\Service\TranslationService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;

class DailyHandlingIndicatorComponentGenerator implements DashboardComponentGenerator {

    public function __construct(
        private DashboardService $dashboardService,
        private TranslationService $translationService,
    ) {
    }

    public function persist(EntityManagerInterface $entityManager,
                            Dashboard\Component    $component): void {
        $config = $component->getConfig();
        $handlingRepository = $entityManager->getRepository(Handling::class);
        $now = new DateTime("now");
        $nowMorning = clone $now;
        $nowMorning->setTime(0, 0, 0, 0);
        $nowEvening = clone $now;
        $nowEvening->setTime(23, 59, 59, 59);
        $handlingStatusesFilter = $config['handlingStatuses'] ?? [];
        $handlingTypesFilter = $config['handlingTypes'] ?? [];

        $numberOfOperations = $handlingRepository->countByDates(
            $nowMorning,
            $nowEvening,
            [
                'isOperations' => true,
                'handlingStatusesFilter' => $handlingStatusesFilter,
                'handlingTypesFilter' => $handlingTypesFilter
            ]
        );

        $numberOfHandlings = $handlingRepository->countByDates(
            $nowMorning,
            $nowEvening,
            [
                'handlingStatusesFilter' => $handlingStatusesFilter,
                'handlingTypesFilter' => $handlingTypesFilter
            ]
        );

        $numberOfEmergenciesHandlings = $handlingRepository->countByDates(
            $nowMorning,
            $nowEvening,
            [
                'emergency' => true,
                'handlingStatusesFilter' => $handlingStatusesFilter,
                'handlingTypesFilter' => $handlingTypesFilter
            ]
        );

        $config = $component->getConfig();
        $config['selectedDate'] = true;
        $component->setConfig($config);

        $meter = $this->dashboardService->persistDashboardMeter($entityManager, $component, DashboardMeter\Indicator::class);
        $secondCount = '<span>'
            . ($numberOfOperations ?? '0')
            . '</span><span class="text-wii-black"> '.$this->translationService->translate('Dashboard', 'lignes').'</span>';
        $thirdCount = '<span class="text-wii-black">'.$this->translationService->translate('Dashboard', 'Dont {1} urgences', [
                1 => '<span class="text-wii-danger">'.$numberOfEmergenciesHandlings.'</span>'
            ]).'</span>';

        $meter
            ->setCount($numberOfHandlings)
            ->setSubCounts([$secondCount, $thirdCount]);
    }
}
