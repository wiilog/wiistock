<?php

namespace App\Service;

use App\Entity\Article;
use App\Entity\MouvementStock;
use App\Entity\ReferenceArticle;
use App\Entity\ScheduledTask\SleepingStockPlan;
use App\Entity\Security\AccessTokenTypeEnum;
use App\Entity\Statut;
use App\Entity\Type;
use App\Entity\Utilisateur;
use App\Security\Authenticator\SleepingStockAuthenticator;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\QueryBuilder;
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
                                      string       $referenceArticleAlias,
                                      string       $articleAlias,
                                      ?Type        $type = null): void {
        $queryBuilder
            ->andWhere(
                $queryBuilder->expr()->orX(
                    $queryBuilder->expr()->andX(
                        "$referenceArticleAlias.quantiteStock > 0",
                        "$referenceArticleAlias.quantiteDisponible > 0"
                    ),
                    "$articleAlias.quantite > 0",
                )
            )

            ->addSelect("
            DATE_ADD($movementAlias.date, $sleepingStockPlanAlias.maxStorageTime, 'second')
            AS test1
            ")

            ->addSelect("
            DATE_ADD(
                        IF(
                            $referenceArticleAlias.lastSleepingStockAlertAnswer IS NULL
                            OR $movementAlias.date > $referenceArticleAlias.lastSleepingStockAlertAnswer,
                            $movementAlias.date,
                            $referenceArticleAlias.lastSleepingStockAlertAnswer
                        ),
                        $sleepingStockPlanAlias.maxStationaryTime,
                         'second'
                    )
            AS test2
            ")

            ->addSelect("
            DATE_ADD(
                        IF(
                            $articleAlias.lastSleepingStockAlertAnswer IS NULL
                            OR $movementAlias.date > $articleAlias.lastSleepingStockAlertAnswer,
                            $movementAlias.date,
                            $articleAlias.lastSleepingStockAlertAnswer
                        ),
                        $sleepingStockPlanAlias.maxStationaryTime,
                         'second'
                    )
            AS test3
            ")

            ->andWhere(
                $queryBuilder->expr()->orX(
                    "DATE_ADD($movementAlias.date, $sleepingStockPlanAlias.maxStorageTime, 'second') < CURRENT_DATE()",

                    $queryBuilder->expr()->andX(
                        "$referenceArticleAlias.id IS NOT NULL",
                        "DATE_ADD(
                            IF(
                                $referenceArticleAlias.lastSleepingStockAlertAnswer IS NULL
                                OR $movementAlias.date > $referenceArticleAlias.lastSleepingStockAlertAnswer,
                                $movementAlias.date,
                                $referenceArticleAlias.lastSleepingStockAlertAnswer
                            ),
                            $sleepingStockPlanAlias.maxStationaryTime,
                             'second'
                        ) < CURRENT_DATE()",
                    ),
                    $queryBuilder->expr()->andX(
                        "$articleAlias.id IS NOT NULL",
                        "DATE_ADD(
                            IF(
                                $articleAlias.lastSleepingStockAlertAnswer IS NULL
                                OR $movementAlias.date > $articleAlias.lastSleepingStockAlertAnswer,
                                $movementAlias.date,
                                $articleAlias.lastSleepingStockAlertAnswer
                            ),
                            $sleepingStockPlanAlias.maxStationaryTime,
                             'second'
                        ) < CURRENT_DATE()",
                    ),
                )
            )
            ->andWhere(
                $queryBuilder->expr()->orX(
                    "statut.code = :articleStatutActif",
                    "statut.code = :referenceStatutActif",
                )
            )
            ->leftJoin(Type::class, $typeAlias, Join::WITH, $queryBuilder->expr()->orX(
                "$typeAlias = $referenceArticleAlias.type",
                "$typeAlias = $articleAlias.type",
            ))
            ->leftJoin(Statut::class , "statut", Join::WITH, $queryBuilder->expr()->orX(
                "statut = reference_article.statut",
                "statut = article.statut",
            ))
            ->innerJoin(SleepingStockPlan::class, "$sleepingStockPlanAlias", Join::WITH,
                $queryBuilder->expr()->andX(
                    "$sleepingStockPlanAlias.type = $typeAlias",
                    "$sleepingStockPlanAlias.enabled = true"
                )
            )
            ->setParameter("articleStatutActif", Article::STATUT_ACTIF)
            ->setParameter("referenceStatutActif", ReferenceArticle::STATUT_ACTIF)
        ;

        if ($type) {
            $queryBuilder
                ->andWhere("$typeAlias = :type")
                ->setParameter("type", $type);
        }
    }
}

