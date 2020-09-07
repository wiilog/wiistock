<?php


namespace App\Service;

use App\Entity\Wiilock;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Exception;


Class WiilockService
{
    /**
     * @param EntityManagerInterface $entityManager
     */
    public function startFeedingDashboard(EntityManagerInterface $entityManager) {
        $wiilockRepository = $entityManager->getRepository(Wiilock::class);
        $dashboardLock = $wiilockRepository->findOneBy([
            'lockKey' => Wiilock::DASHBOARD_FED_KEY
        ]);
        if (!$dashboardLock) {
            $dashboardLock = new Wiilock();
            $entityManager->persist($dashboardLock);
        }
        $dashboardLock
            ->setLockKey(Wiilock::DASHBOARD_FED_KEY)
            ->setValue(true);
    }

    /**
     * @param EntityManagerInterface $entityManager
     * @throws Exception
     */
    public function stopFeedingDashboard(EntityManagerInterface $entityManager) {
        $wiilockRepository = $entityManager->getRepository(Wiilock::class);
        $dashboardLock = $wiilockRepository->findOneBy([
            'lockKey' => Wiilock::DASHBOARD_FED_KEY
        ]);
        if (!$dashboardLock) {
            $dashboardLock = new Wiilock();
            $entityManager->persist($dashboardLock);
        }
        $dashboardLock
            ->setLockKey(Wiilock::DASHBOARD_FED_KEY)
            ->setUpdateDate(new DateTime("now"))
            ->setValue(false);
    }

    /**
     * @param EntityManagerInterface $entityManager
     * @return bool
     */
    public function dashboardIsBeingFed(EntityManagerInterface $entityManager): bool {
        $wiilockRepository = $entityManager->getRepository(Wiilock::class);
        $dashboardLock = $wiilockRepository->findOneBy([
            'lockKey' => Wiilock::DASHBOARD_FED_KEY
        ]);
        return (!empty($dashboardLock) && $dashboardLock->getValue());
    }

    public function getLastDashboardFeedingTime(EntityManagerInterface $entityManager): ?\DateTimeInterface {
        $wiilockRepository = $entityManager->getRepository(Wiilock::class);
        $dashboardLock = $wiilockRepository->findOneBy([
            'lockKey' => Wiilock::DASHBOARD_FED_KEY
        ]);
        return !empty($dashboardLock) ? $dashboardLock->getUpdateDate() : null;
    }
}
