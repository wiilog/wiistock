<?php

namespace App\Service;

use App\Entity\ReferenceArticle;
use App\Entity\ScheduledTask\SleepingStockPlan;
use App\Entity\Security\AccessTokenTypeEnum;
use App\Entity\Utilisateur;
use App\Security\Authenticator\SleepingStockAuthenticator;
use DateInterval;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\RouterInterface;
use Twig\Environment;
use WiiCommon\Helper\Stream;


class SleepingStockPlanService {

    private const MAX_REFERENCE_ARTICLES_IN_ALERT = 10;

    public function __construct(
        private MailerService      $mailerService,
        private Environment        $templating,
        private TranslationService $translationService,
        private FormatService      $formatService,
        private AccessTokenService $accessTokenService,
        private RouterInterface    $router,
    ) {}

    public function triggerSleepingStockPlan(EntityManagerInterface $entityManager,
                                             SleepingStockPlan      $sleepingStockPlan,
                                             DateTime               $taskExecution): void {
        $referenceArticleRepository = $entityManager->getRepository(ReferenceArticle::class);
        $userRepository = $entityManager->getRepository(Utilisateur::class);

        $maxStorageTime = new DateInterval("PT{$sleepingStockPlan->getMaxStorageTime()}S");

        $limitDate = $taskExecution->sub($maxStorageTime);
        $type = $sleepingStockPlan->getType();

        $managerWithSleepingReferenceArticles = $userRepository->findWithSleepingReferenceArticlesByType(
            $type,
            $limitDate
        );

        foreach ($managerWithSleepingReferenceArticles as $manager) {

            $sleepingReferenceArticlesData = $referenceArticleRepository->findSleepingReferenceArticlesByTypeAndManager(
                $manager,
                $limitDate,
                $type,
                self::MAX_REFERENCE_ARTICLES_IN_ALERT,
            );

            // if the user has no sleeping reference articles then we don't need to send an email
            // technically if we are here it means that the user has sleeping reference articles , the condition is just in case somthing goes wrong
            if ($sleepingReferenceArticlesData["countTotal"] == 0) {
                continue;
            }

            $tokenType = AccessTokenTypeEnum::SLEEPING_STOCK;
            $this->accessTokenService->closeActiveTokens($entityManager, $tokenType, $manager);
            $accessToken = $this->accessTokenService->persistAccessToken($entityManager, $tokenType, $manager);
            $entityManager->flush();

            $referenceArticles = Stream::from($sleepingReferenceArticlesData["referenceArticles"])
                ->map(fn(array $referenceArticle) => $this->addMaxStorageDate($referenceArticle, $maxStorageTime))
                ->toArray();

            $this->mailerService->sendMail(
                ['Stock', "Références", "Email stock dormant", 'Seuil d’alerte stock dormant atteint', false],
                $this->templating->render('mails/contents/mailSleepingStockAlert.html.twig', [
                    "urlSuffix" => $this->router->generate("sleeping_stock_index", [SleepingStockAuthenticator::ACCESS_TOKEN_PARAMETER => $accessToken->getPlainToken()]),
                    "countTotal" => $sleepingReferenceArticlesData["countTotal"],
                    "buttonText" => $this->translationService->translate("Stock", "Références", "Email stock dormant", "Cliquez ici pour gérer vos articles", false),
                    "references" => $referenceArticles,
                ]),
                $manager,
            );
        }
    }

    /**
     * @return array{
     *   "id": int,
     *   "reference": string,
     *   "label":  string,
     *   "quantityStock":  int,
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

