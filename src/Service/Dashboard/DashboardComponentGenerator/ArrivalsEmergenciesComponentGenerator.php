<?php

namespace App\Service\Dashboard\DashboardComponentGenerator;

use App\Entity\Dashboard;
use App\Entity\Dashboard\Meter as DashboardMeter;
use App\Entity\Urgence;
use App\Service\Dashboard\DashboardService;
use Doctrine\ORM\EntityManagerInterface;

class ArrivalsEmergenciesComponentGenerator implements DashboardComponentGenerator {

    public function __construct(
        private DashboardService $dashboardService,
    ) {
    }

    public function persist(EntityManagerInterface $entityManager,
                            Dashboard\Component    $component): void {
        $componentType = $component->getType();
        $meterKey = $componentType->getMeterKey();

        $daily = $meterKey === Dashboard\ComponentType::DAILY_ARRIVALS_EMERGENCIES;
        $active = $meterKey === Dashboard\ComponentType::ARRIVALS_EMERGENCIES_TO_RECEIVE;

        $meter = $this->dashboardService->persistDashboardMeter($entityManager, $component, DashboardMeter\Indicator::class);

        // TODO WIIS-12768
        /*
         * Dans UrgenceRepository
         public function countUnsolved(bool $daily = false, bool $active = false) {
        $queryBuilder = $this->createQueryBuilder('urgence')
            ->select('COUNT(urgence)')
            ->where('urgence.dateStart < :now')
            ->andWhere('urgence.lastArrival IS NULL')
            ->setParameter('now', new DateTime('now'));

        if ($daily) {
            $todayEvening = new DateTime('now');
            $todayEvening->setTime(23, 59, 59, 59);
            $todayMorning = new DateTime('now');
            $todayMorning->setTime(0, 0, 0, 1);
            $queryBuilder
                ->andWhere('urgence.dateEnd < :todayEvening')
                ->andWhere('urgence.dateEnd > :todayMorning')
                ->setParameter('todayEvening', $todayEvening)
                ->setParameter('todayMorning', $todayMorning);
        }

        if ($active) {
            $today = new DateTime('now');
            $queryBuilder
                ->andWhere('urgence.dateEnd >= :todayEvening')
                ->setParameter('todayEvening', $today);
        }

        return $queryBuilder
            ->getQuery()
            ->getSingleScalarResult();
    }
         */
        $emergencyRepository = $entityManager->getRepository(Urgence::class);
        $unsolvedEmergencies = $emergencyRepository->countUnsolved($daily, $active);
        $meter
            ->setCount($unsolvedEmergencies ?? 0);
    }
}
