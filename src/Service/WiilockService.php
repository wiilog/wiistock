<?php


namespace App\Service;

use App\Entity\Wiilock;
use DateTime;
use DateTimeInterface;
use DateTimeZone;
use Doctrine\ORM\EntityManagerInterface;


Class WiilockService
{

    /**
     * @param EntityManagerInterface $entityManager
     * @param bool $lock
     * @throws \Exception
     */
    public function toggleFeedingDashboard(EntityManagerInterface $entityManager, bool $lock) {
        $wiilockRepository = $entityManager->getRepository(Wiilock::class);
        $dashboardLock = $wiilockRepository->findOneBy(['lockKey' => Wiilock::DASHBOARD_FED_KEY]);
        if (!$dashboardLock) {
            $dashboardLock = new Wiilock();
            $dashboardLock->setLockKey(Wiilock::DASHBOARD_FED_KEY);
            $entityManager->persist($dashboardLock);
        }

        $dashboardLock->setValue($lock);

        if (!$lock) {
            $dashboardLock->setUpdateDate(new DateTime('now', new DateTimeZone('Europe/Paris')));
        }
    }

    /**
     * @param EntityManagerInterface $entityManager
     * @return bool
     */
    public function dashboardIsBeingFed(EntityManagerInterface $entityManager): bool {
        $wiilockRepository = $entityManager->getRepository(Wiilock::class);
        $dashboardLock = $wiilockRepository->findOneBy(['lockKey' => Wiilock::DASHBOARD_FED_KEY]);
        return (!empty($dashboardLock) && $dashboardLock->getValue());
    }

    /**
     * @param EntityManagerInterface $entityManager
     * @return DateTimeInterface|null
     */
    public function getLastDashboardFeedingTime(EntityManagerInterface $entityManager): ?DateTimeInterface {
        $wiilockRepository = $entityManager->getRepository(Wiilock::class);
        $dashboardLock = $wiilockRepository->findOneBy(['lockKey' => Wiilock::DASHBOARD_FED_KEY]);
        return !empty($dashboardLock) ? $dashboardLock->getUpdateDate() : null;
    }
}
