<?php

namespace App\Service;

use App\Entity\ReferenceArticle;
use App\Entity\ScheduledTask\SleepingStockPlan;
use App\Entity\Utilisateur;
use DateInterval;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Twig\Environment;
use WiiCommon\Helper\Stream;


class SleepingStockPlanService {

    public function __construct(
        private MailerService      $mailerService,
        private Environment        $templating,
        private TranslationService $translationService,
        private FormatService      $formatService
    ) {}

    public const MAX_REFERENCE_ARTICLES_IN_ALERT = 10;

    public function triggerSleepingStockPlan(EntityManagerInterface $entityManager, SleepingStockPlan $sleepingStockPlan, DateTime $taskExecution): void {
        $referenceArticleRepository = $entityManager->getRepository(ReferenceArticle::class);
        $userRepository = $entityManager->getRepository(Utilisateur::class);

        $maxStorageTime = new DateInterval("PT{$sleepingStockPlan->getMaxStorageTime()}S");

        $limitDate = $taskExecution->sub($maxStorageTime);
        $type = $sleepingStockPlan->getType();

        $ManagerWithSleepingReferenceArticles = $userRepository->findWithSleepingReferenceArticlesByType(
            $type,
            $limitDate
        );

        foreach ($ManagerWithSleepingReferenceArticles as $manager) {
            $sleepingReferenceArticlesData = $referenceArticleRepository->findSleepingReferenceArticlesByTypeAndManager(
                $manager,
                $limitDate,
                $type,
            );

            // if the user has no sleeping reference articles then we don't need to send an email
            // technically if we are here it means that the user has sleeping reference articles , the condition is just in case somthing goes wrong
            if ($sleepingReferenceArticlesData["countTotal"] == 0) {
                continue;
            }


            // TODO GENERATE TOKEN

            // TODO IF THE TOKEN IS FLUSHED
            // WE CAN SEND THE EMAIL

            $referenceArticles = Stream::from($sleepingReferenceArticlesData["referenceArticles"])
                ->map(fn(array $referenceArticle) => $this->addMaxStorageDate($referenceArticle, $maxStorageTime))
                ->toArray();

            dump($referenceArticles);

            $this->mailerService->sendMail(
                ['Stock', "Références", "Email stock dormant", 'Seuil d’alerte stock dormant atteint', false],
                $this->templating->render('mails/contents/mailSleepingStockAlert.html.twig', [
                    "urlSuffix" => "",
                    "countTotal" => $sleepingReferenceArticlesData["countTotal"],
                    "buttonText" => $this->translationService->translate("Stock", "Références", "Email stock dormant", "Cliquez ici pour gérer vos articles", false),
                    "references" => $referenceArticles,
                ]),
                $manager,
            );

            die; // TODO
        }
    }

    /**
     * @return array{
     *   "id": int,
     *   "reference": string,
     *   "label":  string,
     *   "quantityStock":  int,
     *   "__query_count": int,
     *   "lastMovementDate": string,
     *   "maxStorageDate": string,
     * }
     */
    private function addMaxStorageDate(array $referenceArticle, DateInterval $maxStorageTime): array {
        $lastMovementDate = new DateTime($referenceArticle["lastMovementDate"]);
        $maxStorageDate = $lastMovementDate->add($maxStorageTime);
        $this->formatService->dateTime($maxStorageDate);
        return [
            ...$referenceArticle,
            "maxStorageDate" => $this->formatService->dateTime($maxStorageDate),
        ];
    }
}

