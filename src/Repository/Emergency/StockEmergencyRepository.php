<?php

namespace App\Repository\Emergency;


use App\Entity\Emergency\EmergencyTriggerEnum;
use App\Entity\Emergency\EndEmergencyCriteriaEnum;
use App\Entity\ReferenceArticle;
use App\Service\FormatService;
use DateTime;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query\Expr\Join;

/**
 * @extends EmergencyRepository<StockEmergencyRepository>
 */
class StockEmergencyRepository extends EntityRepository {

    public function getEmergencyTriggeredByRefArticle(FormatService    $formatService,
                                                      ReferenceArticle $referenceArticle,
                                                      DateTime         $now) {
        $queryBuilder = $this->createQueryBuilder('stock_emergency');
        $exprBuilder = $queryBuilder->expr();

        $queryBuilder
            ->leftJoin('stock_emergency.referenceArticle', 'join_emergency_reference_article')
            ->leftJoin(ReferenceArticle::class, 'join_reference_article', Join::WITH, 'join_reference_article = :referenceArticle')
            ->leftJoin('join_reference_article.articlesFournisseur', 'join_article_fournisseur')
            ->andWhere($exprBuilder->orX(
                // when emergencyTrigger is reference
                $exprBuilder->andX(
                    $exprBuilder->eq("stock_emergency.emergencyTrigger", ":emergencyTriggerReference"),
                    $exprBuilder->eq("join_emergency_reference_article", ":referenceArticle"),
                    $exprBuilder->orX(
                        // when endEmergencyCriteria is quantity
                        $exprBuilder->andX(
                            $exprBuilder->eq("stock_emergency.endEmergencyCriteria", ":endEmergencyCriteriaRemainingQuantity"),
                            $exprBuilder->gt("stock_emergency.expectedQuantity", "stock_emergency.alreadyReceivedQuantity")
                        ),
                        // when endEmergencyCriteria is end date
                        $exprBuilder->andX(
                            $exprBuilder->eq("stock_emergency.endEmergencyCriteria", ":endEmergencyCriteriaEndDate"),
                            $exprBuilder->between(":now", "stock_emergency.dateStart", "stock_emergency.dateEnd"),
                        ),
                        // when endEmergencyCriteria is manual
                        $exprBuilder->andX(
                            $exprBuilder->eq("stock_emergency.endEmergencyCriteria", ":endEmergencyCriteriaManual"),
                            $exprBuilder->lt("stock_emergency.dateStart", ":now")
                        ),
                    ),
                ),
                // when emergencyTrigger is supplier
                $exprBuilder->andX(
                    $exprBuilder->eq("stock_emergency.emergencyTrigger", ":emergencyTriggerSupplier"),
                    $exprBuilder->eq("stock_emergency.supplier", "join_article_fournisseur.fournisseur"),
                    $exprBuilder->orX(
                        // when endEmergencyCriteria is end date
                        $exprBuilder->andX(
                            $exprBuilder->eq("stock_emergency.endEmergencyCriteria", ":endEmergencyCriteriaEndDate"),
                            $exprBuilder->between(":now", "stock_emergency.dateStart", "stock_emergency.dateEnd"),
                        ),
                        // when endEmergencyCriteria is manual
                        $exprBuilder->andX(
                            $exprBuilder->eq("stock_emergency.endEmergencyCriteria", ":endEmergencyCriteriaManual"),
                            $exprBuilder->lt("stock_emergency.dateStart", ":now")
                        ),
                    ),
                ),
            ))
            ->andWhere("stock_emergency.closedAt IS NULL")
            ->setParameters([
                "referenceArticle" => $referenceArticle,
                "emergencyTriggerReference" => EmergencyTriggerEnum::REFERENCE,
                "emergencyTriggerSupplier" => EmergencyTriggerEnum::SUPPLIER,
                "endEmergencyCriteriaRemainingQuantity" => EndEmergencyCriteriaEnum::REMAINING_QUANTITY,
                "endEmergencyCriteriaEndDate" => EndEmergencyCriteriaEnum::END_DATE,
                "endEmergencyCriteriaManual" => EndEmergencyCriteriaEnum::MANUAL,
                "now" => $now,
            ]);

        return $queryBuilder->getQuery()->getResult();
    }
}
