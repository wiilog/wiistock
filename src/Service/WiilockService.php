<?php


namespace App\Service;

use App\Entity\Wiilock;
use DateTime;
use DateTimeInterface;
use Doctrine\ORM\EntityManagerInterface;


Class WiilockService
{

    /**
     * @param EntityManagerInterface $entityManager
     * @param bool $lock
     * @param string $type
     * @throws \Exception
     */
    public function toggleFeedingCommand(EntityManagerInterface $entityManager, bool $lock, string $type) {
        $wiilockRepository = $entityManager->getRepository(Wiilock::class);
        $commandLock = $wiilockRepository->findOneBy(['lockKey' => $type]);
        if (!$commandLock) {
            $commandLock = new Wiilock();
            $commandLock->setLockKey($type);
            $entityManager->persist($commandLock);
        }

        $commandLock->setValue($lock);

        if (!$lock) {
            $commandLock->setUpdateDate(new DateTime('now'));
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

    public function dashboardNeedsFeeding(EntityManagerInterface $entityManager) {
        $now = new DateTime('now');
        $lastUpdate = $this->getLastDashboardFeedingTime($entityManager);

        return !$this->dashboardIsBeingFed($entityManager) || $lastUpdate->diff($now)->i >= 15;
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
