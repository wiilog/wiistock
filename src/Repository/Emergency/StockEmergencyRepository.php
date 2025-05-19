<?php

namespace App\Repository\Emergency;


use App\Entity\Emergency\EmergencyTriggerEnum;
use App\Entity\Emergency\EndEmergencyCriteriaEnum;
use App\Entity\ReferenceArticle;
use DateTime;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\QueryBuilder;

/**
 * @extends EmergencyRepository<StockEmergencyRepository>
 */
class StockEmergencyRepository extends EntityRepository {

    public function findEmergencyTriggeredByRefArticle(ReferenceArticle $referenceArticle,
                                                       DateTime         $now) {
        $queryBuilder = $this->createQueryBuilder('stock_emergency');

        self::filterByReferenceByRefArticle(
            $queryBuilder,
            $now,
            $referenceArticle->getId(),
            'stock_emergency'
        );

        return $queryBuilder->getQuery()->getResult();
    }

    public static function filterByReferenceByRefArticle(QueryBuilder $queryBuilder,
                                                         DateTime     $now,
                                                         int          $referenceArticleId,
                                                         string       $stockEmergencyAlias): void {
        $exprBuilder = $queryBuilder->expr();

        $queryBuilder
            ->leftJoin("$stockEmergencyAlias.referenceArticle", 'join_emergency_reference_article')
            ->leftJoin(ReferenceArticle::class, 'join_reference_article', Join::WITH, $exprBuilder->eq("join_reference_article.id", ":referenceArticleId"))
            ->leftJoin('join_reference_article.articlesFournisseur', 'join_article_fournisseur')
            ->andWhere($exprBuilder->orX(
                // when emergencyTrigger is reference
                $exprBuilder->andX(
                    $exprBuilder->eq("$stockEmergencyAlias.emergencyTrigger", ":emergencyTriggerReference"),
                    $exprBuilder->eq("join_emergency_reference_article.id", ":referenceArticleId"),
                    $exprBuilder->orX(
                    // when endEmergencyCriteria is quantity
                        $exprBuilder->andX(
                            $exprBuilder->eq("$stockEmergencyAlias.endEmergencyCriteria", ":endEmergencyCriteriaRemainingQuantity"),
                            $exprBuilder->gt("$stockEmergencyAlias.expectedQuantity", "(CASE WHEN $stockEmergencyAlias.alreadyReceivedQuantity IS NULL THEN 0 ELSE $stockEmergencyAlias.alreadyReceivedQuantity END)")
                        ),
                        // when endEmergencyCriteria is end date
                        $exprBuilder->andX(
                            $exprBuilder->eq("$stockEmergencyAlias.endEmergencyCriteria", ":endEmergencyCriteriaEndDate"),
                            $exprBuilder->between(":now", "$stockEmergencyAlias.dateStart", "$stockEmergencyAlias.dateEnd"),
                        ),
                        // when endEmergencyCriteria is manual
                        $exprBuilder->andX(
                            $exprBuilder->eq("$stockEmergencyAlias.endEmergencyCriteria", ":endEmergencyCriteriaManual"),
                            $exprBuilder->lt("$stockEmergencyAlias.dateStart", ":now")
                        ),
                    ),
                ),
                // when emergencyTrigger is supplier
                $exprBuilder->andX(
                    $exprBuilder->eq("$stockEmergencyAlias.emergencyTrigger", ":emergencyTriggerSupplier"),
                    $exprBuilder->eq("$stockEmergencyAlias.supplier", "join_article_fournisseur.fournisseur"),
                    $exprBuilder->orX(
                    // when endEmergencyCriteria is end date
                        $exprBuilder->andX(
                            $exprBuilder->eq("$stockEmergencyAlias.endEmergencyCriteria", ":endEmergencyCriteriaEndDate"),
                            $exprBuilder->between(":now", "$stockEmergencyAlias.dateStart", "$stockEmergencyAlias.dateEnd"),
                        ),
                        // when endEmergencyCriteria is manual
                        $exprBuilder->andX(
                            $exprBuilder->eq("$stockEmergencyAlias.endEmergencyCriteria", ":endEmergencyCriteriaManual"),
                            $exprBuilder->lt("$stockEmergencyAlias.dateStart", ":now")
                        ),
                    ),
                ),
            ))
            ->andWhere($exprBuilder->isNull("stock_emergency.closedAt"))
            ->setParameter("referenceArticleId", $referenceArticleId)
            ->setParameter("emergencyTriggerReference", EmergencyTriggerEnum::REFERENCE)
            ->setParameter("emergencyTriggerSupplier", EmergencyTriggerEnum::SUPPLIER)
            ->setParameter("endEmergencyCriteriaRemainingQuantity", EndEmergencyCriteriaEnum::REMAINING_QUANTITY)
            ->setParameter("endEmergencyCriteriaEndDate", EndEmergencyCriteriaEnum::END_DATE)
            ->setParameter("endEmergencyCriteriaManual", EndEmergencyCriteriaEnum::MANUAL)
            ->setParameter("now", $now);
    }
}
