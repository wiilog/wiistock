<?php

namespace App\Service;

use App\Entity\ReferenceArticle;
use App\Entity\ScheduledTask\SleepingStockPlan;
use App\Entity\Utilisateur;
use App\Repository\ScheduledTask\SleepingStockPlanRepository;
use DateInterval;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;

class SleepingStockPlanService {

    public const MAX_REFERENCE_ARTICLES_IN_ALERT = 10;

    public function triggerSleepingStockPlan(EntityManagerInterface $entityManager, SleepingStockPlan $sleepingStockPlan, DateTime $taskExecution): void {
        $referenceArticleRepository = $entityManager->getRepository(ReferenceArticle::class);
        $userRepository = $entityManager->getRepository(Utilisateur::class);

        $limitDate = $taskExecution->sub(new DateInterval("PT{$sleepingStockPlan->getMaxStorageTime()}S"));
        $type = $sleepingStockPlan->getType();

        $ManagerWithSleepingReferenceArticles = $userRepository->findWithSleepingReferenceArticlesByType(
            $type,
            $limitDate
        );

        foreach ($ManagerWithSleepingReferenceArticles as $manager) {
            $sleepingReferenceArticles = $referenceArticleRepository->findSleepingReferenceArticlesByTypeAndManager(
                $manager,
                $limitDate,
                $type,
            );

            dump($sleepingReferenceArticles);
        }
    }
}

