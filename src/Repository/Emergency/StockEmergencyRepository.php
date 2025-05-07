<?php

namespace App\Repository\Emergency;


use App\Entity\ArticleFournisseur;
use App\Entity\Fournisseur;
use App\Entity\ReferenceArticle;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query\Expr\Join;
use WiiCommon\Helper\Stream;

/**
 * @extends EmergencyRepository<StockEmergencyRepository>
 */
class StockEmergencyRepository extends EntityRepository {

    public function getEmergencyTriggeredByRefArticle(ReferenceArticle $referenceArticle) {
        $queryBuilder = $this->createQueryBuilder('stock_emergency');
        $exprBuilder = $queryBuilder->expr();

        $referenceArticleSuppliers = Stream::from($referenceArticle->getArticlesFournisseur())
            ->map(static fn(ArticleFournisseur $supplierArticle) => $supplierArticle->getFournisseur())
            ->unique()
            ->toArray();

        $queryBuilder
            ->andWhere($exprBuilder->orX(
                "stock_emergency.referenceArticle = :referenceArticle",
                "stock_emergency.supplier IN (:referenceArticleSuppliers)"
            ))
            ->andWhere("stock_emergency.closedAt IS NULL")
            ->setParameters([
                "referenceArticle" => $referenceArticle,
                "referenceArticleSuppliers" => $referenceArticleSuppliers,
            ]);

        return $queryBuilder->getQuery()->getResult();
    }
}
