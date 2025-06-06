<?php

namespace App\Repository\Emergency;


use App\Entity\Emergency\EmergencyTriggerEnum;
use App\Entity\Emergency\EndEmergencyCriteriaEnum;
use App\Entity\ReferenceArticle;
use DateTime;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query\Expr;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\QueryBuilder;

/**
 * @extends EmergencyRepository<StockEmergencyRepository>
 */
class StockEmergencyRepository extends EntityRepository {

    public function findEmergencyTriggeredByRefArticle(ReferenceArticle $referenceArticle,
                                                       DateTime         $now) {
        $queryBuilder = $this->createQueryBuilder('stock_emergency');

        $exprBuilder = $queryBuilder->expr();

        $queryBuilder
            ->leftJoin("stock_emergency.supplier", "stock_emergency_supplier")
            ->leftJoin("stock_emergency_supplier.articlesFournisseur", "stock_emergency_supplier_article")
            ->innerJoin(ReferenceArticle::class, 'join_reference_article', Join::WITH,
                $exprBuilder->andX(
                    $exprBuilder->orX(
                        $exprBuilder->eq("join_reference_article", "stock_emergency.referenceArticle"),
                        $exprBuilder->eq("stock_emergency_supplier_article.referenceArticle", "join_reference_article"),
                    ),
                    $exprBuilder->eq("join_reference_article", ":referenceArticle"),
                )
            )
            ->andWhere(StockEmergencyRepository::getTriggerableStockEmergenciesCondition($exprBuilder, 'stock_emergency'))
            ->setParameter("referenceArticle", $referenceArticle)
            ->setParameter("emergencyTriggerReference", EmergencyTriggerEnum::REFERENCE)
            ->setParameter("emergencyTriggerSupplier", EmergencyTriggerEnum::SUPPLIER)
            ->setParameter("endEmergencyCriteriaRemainingQuantity", EndEmergencyCriteriaEnum::REMAINING_QUANTITY)
            ->setParameter("endEmergencyCriteriaEndDate", EndEmergencyCriteriaEnum::END_DATE)
            ->setParameter("endEmergencyCriteriaManual", EndEmergencyCriteriaEnum::MANUAL)
            ->setParameter("now", $now);

        return $queryBuilder->getQuery()->getResult();
    }

    public static function getTriggerableStockEmergenciesCondition(Expr   $exprBuilder,
                                                                   string $stockEmergencyAlias): Expr\Andx {
        return $exprBuilder->andX(
            $exprBuilder->isNotNull("$stockEmergencyAlias.id"),
            $exprBuilder->isNull("$stockEmergencyAlias.closedAt"),
            $exprBuilder->orX(
            // when emergencyTrigger is reference
                $exprBuilder->andX(
                    $exprBuilder->eq("$stockEmergencyAlias.emergencyTrigger", ":emergencyTriggerReference"),
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
            ),
        );
    }
}
