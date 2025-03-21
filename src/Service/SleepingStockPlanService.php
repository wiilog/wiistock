<?php

namespace App\Service;

use App\Entity\Article;
use App\Entity\MouvementStock;
use App\Entity\ScheduledTask\SleepingStockPlan;
use App\Entity\Security\AccessTokenTypeEnum;
use App\Entity\Type;
use App\Entity\Utilisateur;
use App\Security\Authenticator\SleepingStockAuthenticator;
use DateInterval;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Routing\RouterInterface;
use Twig\Environment;


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
        $movementStockRepository = $entityManager->getRepository(MouvementStock::class);
        $userRepository = $entityManager->getRepository(Utilisateur::class);
        $type = $sleepingStockPlan->getType();

        $managerWithSleepingReferenceArticles = $userRepository->findWithSleepingReferenceArticlesByType(
            $type,
            $this,
        );

        foreach ($managerWithSleepingReferenceArticles as $manager) {
            $sleepingReferenceArticlesData = $movementStockRepository->findForSleepingStock(
                $manager,
                self::MAX_REFERENCE_ARTICLES_IN_ALERT,
                $this,
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

    public function findSleepingStock(QueryBuilder $queryBuilder,
                                      string       $sleepingStockPlanAlias,
                                      string       $typeAlias,
                                      string       $movementAlias,
                                      ?Type        $type = null): void {
        // TODO WIIS-12522 : and where active = true

        // TODO more aliases ?

        $queryBuilder
            ->andWhere(
                $queryBuilder->expr()->orX(
                    $queryBuilder->expr()->andX(
                        "reference_article.quantiteStock > 0",
                        "reference_article.quantiteDisponible > 0"
                    ),
                    $queryBuilder->expr()->andX(
                        "article.quantite > 0",
                        "article_statut.code = :statutActif"
                    )
                )
            )
            ->andWhere("DATE_ADD($movementAlias.date, $sleepingStockPlanAlias.maxStorageTime, 'second') < CURRENT_DATE()")
            ->leftJoin(Type::class, $typeAlias, Join::WITH, "$typeAlias = reference_article.type OR $typeAlias = article.type")
            ->leftJoin("article.statut", "article_statut")
            ->innerJoin(SleepingStockPlan::class, "$sleepingStockPlanAlias", Join::WITH, "$sleepingStockPlanAlias.type = $typeAlias")
            ->setParameter("statutActif", Article::STATUT_ACTIF);

        if ($type) {
            $queryBuilder
                ->andWhere("$typeAlias = :type")
                ->setParameter("type", $type);
        }
    }
}

