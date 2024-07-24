<?php

namespace App\Repository\ScheduledTask\StorageRule;

use App\Entity\Article;
use App\Entity\Fournisseur;
use App\Entity\ScheduledTask\PurchaseRequestPlan;
use App\Entity\StorageRule;
use App\Entity\Zone;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query\Expr\Join;
use WiiCommon\Helper\Stream;

/**
 * @extends EntityRepository<StorageRule>
 *
 * @method StorageRule|null find($id, $lockMode = null, $lockVersion = null)
 * @method StorageRule|null findOneBy(array $criteria, array $orderBy = null)
 * @method StorageRule[]    findAll()
 * @method StorageRule[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class StorageRuleRepository extends EntityRepository
{
    public function clearTable(): void {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "DELETE
            FROM App\Entity\StorageRule sr
           "
        );
        $query->execute();
    }

    public function findOneByReferenceAndLocation(string $reference, string $location): StorageRule|null {
        return $this->createQueryBuilder("storage_rule")
            ->leftJoin("storage_rule.location", "location")
            ->leftJoin("storage_rule.referenceArticle", "reference_article")
            ->andWhere("reference_article.reference = :reference AND location.label = :location")
            ->setParameter("reference", "$reference")
            ->setParameter("location", $location)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function iterateAll(): iterable {
        $qb = $this->createQueryBuilder('storage_rule')
            ->select('reference.reference')
            ->addSelect('location.label AS locationLabel')
            ->addSelect('storage_rule.securityQuantity AS securityQuantity')
            ->addSelect('storage_rule.conditioningQuantity AS conditioningQuantity')
            ->addSelect('zone.name AS zoneName')
            ->leftjoin('storage_rule.referenceArticle', 'reference')
            ->leftjoin('storage_rule.location', 'location')
            ->leftJoin('location.zone', 'zone');

        return $qb
            ->getQuery()
            ->toIterable();
    }

    public function findByPlanWithStockQuantity(PurchaseRequestPlan $requestPlan) {
        return $this->createQueryBuilder('storage_rule')
            ->join('storage_rule.referenceArticle', 'join_referenceArticleRule')
            ->join('join_referenceArticleRule.articlesFournisseur', 'join_articlesFournisseurRef')
            ->leftjoin('join_articlesFournisseurRef.articles', 'join_article', Join::WITH, 'join_article.emplacement = storage_rule.location')
            ->leftjoin('join_article.statut', 'join_statut')
            ->join('storage_rule.location', 'join_location')
            ->join('join_location.zone', 'join_zone')
            ->join('join_articlesFournisseurRef.fournisseur', 'join_fournisseur')
            ->andWhere('join_zone.id in (:zones)')
            ->andWhere('join_fournisseur.id in (:suppliers)')
            ->andHaving('(SUM(IF(join_statut.code = :available, join_article.quantite, 0)) - MAX(IF(join_statut.code = :available, join_article.quantite, 0))) < storage_rule.securityQuantity')
            ->setParameters([
                'zones' => $requestPlan->getZones(),
                'suppliers' => $requestPlan->getSuppliers(),
                'available' => Article::STATUT_ACTIF
            ])
            ->groupBy('storage_rule')
            ->getQuery()
            ->getResult();
    }
}
