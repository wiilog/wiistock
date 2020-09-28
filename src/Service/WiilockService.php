<?php


namespace App\Service;

use App\Entity\Wiilock;
use DateTime;
use DateTimeInterface;
use Doctrine\ORM\EntityManagerInterface;
use Exception;


Class WiilockService
{
    /**
     * @param EntityManagerInterface $entityManager
     * @param string $meterType
     */
    public function startFeedingDashboard(EntityManagerInterface $entityManager, string $meterType) {
        $wiilockRepository = $entityManager->getRepository(Wiilock::class);
        $dashboardLock = $wiilockRepository->findOneBy([
            'lockKey' => $meterType
        ]);
        if (!$dashboardLock) {
            $dashboardLock = new Wiilock();
            $entityManager->persist($dashboardLock);
        }
        $dashboardLock
            ->setLockKey($meterType)
            ->setValue(true);
    }

    /**
     * @param EntityManagerInterface $entityManager
     * @param string $meterType
     */
    public function stopFeedingDashboard(EntityManagerInterface $entityManager, string $meterType) {
        $wiilockRepository = $entityManager->getRepository(Wiilock::class);
        $dashboardLock = $wiilockRepository->findOneBy([
            'lockKey' => $meterType
        ]);
        if (!$dashboardLock) {
            $dashboardLock = new Wiilock();
            $entityManager->persist($dashboardLock);
        }
        $dashboardLock
            ->setLockKey($meterType)
            ->setUpdateDate(new DateTime("now"))
            ->setValue(false);
    }

    /**
     * @param EntityManagerInterface $entityManager
     * @param string $meterType
     * @return bool
     */
    public function dashboardIsBeingFed(EntityManagerInterface $entityManager, string $meterType): bool {
        $wiilockRepository = $entityManager->getRepository(Wiilock::class);
        $dashboardLock = $wiilockRepository->findOneBy([
            'lockKey' => $meterType
        ]);
        return (!empty($dashboardLock) && $dashboardLock->getValue());
    }

    /**
     * @param EntityManagerInterface $entityManager
     * @param string $meterType
     * @return DateTimeInterface|null
     */
    public function getLastDashboardFeedingTime(EntityManagerInterface $entityManager, string $meterType): ?DateTimeInterface {
        $wiilockRepository = $entityManager->getRepository(Wiilock::class);
        $dashboardLock = $wiilockRepository->findOneBy([
            'lockKey' => $meterType
        ]);
        return !empty($dashboardLock) ? $dashboardLock->getUpdateDate() : null;
    }
}
