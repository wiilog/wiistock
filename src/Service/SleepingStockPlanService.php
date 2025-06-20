<?php

namespace App\Service;

use App\Entity\MouvementStock;
use App\Entity\ScheduledTask\SleepingStockPlan;
use App\Entity\Security\AccessTokenTypeEnum;
use App\Entity\Utilisateur;
use App\Security\Authenticator\SleepingStockAuthenticator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\RouterInterface;
use Twig\Environment;


class SleepingStockPlanService {

    private const MAX_REFERENCE_ARTICLES_IN_ALERT = 10;

    public function __construct(
        private MailerService      $mailerService,
        private Environment        $templating,
        private TranslationService $translationService,
        private AccessTokenService $accessTokenService,
        private RouterInterface    $router,
    ) {}

    public function triggerSleepingStockPlan(EntityManagerInterface $entityManager,
                                             SleepingStockPlan      $sleepingStockPlan): void {
        $movementStockRepository = $entityManager->getRepository(MouvementStock::class);
        $userRepository = $entityManager->getRepository(Utilisateur::class);
        $type = $sleepingStockPlan->getType();

        $managerWithSleepingReferenceArticles = $userRepository->findWithSleepingReferenceArticlesByType($type);

        foreach ($managerWithSleepingReferenceArticles as $manager) {
            if (!$manager->hasSleepingStockRightAccess()) {
                continue;
            }

            $sleepingReferenceArticlesData = $movementStockRepository->findForSleepingStock(
                $manager,
                self::MAX_REFERENCE_ARTICLES_IN_ALERT,
                $type,
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

            $this->mailerService->sendMail(
                $entityManager,
                ['Stock', "Références", "Email stock dormant", 'Seuil d’alerte stock dormant atteint', false],
                $this->templating->render('mails/contents/mailSleepingStockAlert.html.twig', [
                    "urlSuffix" => $this->router->generate("sleeping_stock_index", [SleepingStockAuthenticator::ACCESS_TOKEN_PARAMETER => $accessToken->getPlainToken()]),
                    "countTotal" => $sleepingReferenceArticlesData["countTotal"],
                    "buttonText" => $this->translationService->translate("Stock", "Références", "Email stock dormant", "Cliquez ici pour gérer vos articles", false),
                    "references" => $sleepingReferenceArticlesData["referenceArticles"],
                ]),
                $manager,
            );
        }
    }
}

