<?php

namespace App\Repository\Inventory;

use App\Entity\Article;
use App\Entity\Inventory\InventoryEntry;
use App\Entity\Livraison;
use App\Entity\PreparationOrder\Preparation;
use App\Entity\ReferenceArticle;
use App\Helper\QueryBuilderHelper;
use DateTime;
use Doctrine\ORM\EntityRepository;
use Symfony\Component\HttpFoundation\InputBag;

class InventoryEntryRepository extends EntityRepository
{
	private const DtToDbLabels = [
		'Ref' => 'reference',
		'Label' => 'label',
		'Date' => 'date',
		'Location' => 'location',
		'Operator' => 'operator',
		'Quantity' => 'quantity',
		'barCode' => 'barCode',
	];

    public function getAnomalies(bool $forceValidLocation = false, array $anomalyIds = []): array {
        $queryBuilder = $this->createQueryBuilder("inventory_entry");

        $subQueryCallback = function(?bool $isArticle = false): string {
            if($isArticle) {
                return $this->createQueryBuilder("sub_inventory_entry")
                    ->select("
                        IF(
                            sub_articleStatus.nom IN (:articleStatusAvailable, :articleStatusDispute),
                            1,
                            0
                        )
                    ")
                    ->innerJoin("sub_inventory_entry.article", "sub_article")
                    ->innerJoin("sub_article.statut", "sub_articleStatus")
                    ->andWhere("sub_inventory_entry.id = inventory_entry.id")
                    ->groupBy("sub_inventory_entry.id")
                    ->getQuery()
                    ->getDQL();
            } else {
                return "
                    MIN(IF((
                          join_referenceArticleStatus.nom = :referenceStatusAvailable
                          AND (
                              join_preparationOrderReferenceLines.id IS NULL
                              OR (
                                      join_preparationStatus.nom != :preparationStatusToTreat
                                  AND join_preparationStatus.nom != :preparationStatusInProgress
                                  AND
                                      (
                                          join_deliveryOrder.id IS NULL
                                          OR join_deliveryOrderStatus.nom != :deliveryOrderStatusToTreat
                                      )
                              )
                          )
                    ), 1, 0))
                ";
            }
        };

        $queryBuilder
            ->distinct()
            ->select("inventory_entry.id AS id")
            ->addSelect("COALESCE(join_articleReferenceArticle.reference, join_referenceArticle.reference) AS reference")
            ->addSelect("COALESCE(join_article.label, join_referenceArticle.libelle) AS label")
            ->addSelect("COALESCE(join_articleLocation.label, join_referenceArticleLocation.label) AS location")
            ->addSelect("COALESCE(join_article.quantite, join_referenceArticle.quantiteStock) AS quantity")
            ->addSelect("inventory_entry.quantity AS countedQuantity")
            ->addSelect("IF(join_article.id IS NOT NULL, 0, 1) AS is_ref")
            ->addSelect("0 AS treated")
            ->addSelect("COALESCE(join_article.barCode, join_referenceArticle.barCode) AS barCode")
            ->addSelect("IF(
                join_article.id IS NOT NULL,
                ({$subQueryCallback(true)}),
                ({$subQueryCallback()})
            ) AS isTreatable")
            ->addSelect("join_inventoryMission.id AS mission_id")
            ->addSelect("join_inventoryMission.startPrevDate AS mission_start")
            ->addSelect("join_inventoryMission.endPrevDate AS mission_end")
            ->addSelect("join_inventoryMission.name AS mission_name")
            ->leftJoin("inventory_entry.refArticle", "join_referenceArticle")
            ->leftJoin("join_referenceArticle.statut", "join_referenceArticleStatus")
            ->leftJoin("join_referenceArticle.emplacement", "join_referenceArticleLocation")
            ->leftJoin("join_referenceArticle.preparationOrderReferenceLines", "join_preparationOrderReferenceLines")
            ->leftJoin("inventory_entry.article", "join_article")
            ->leftJoin("join_article.articleFournisseur", "join_supplierArticle")
            ->leftJoin("join_supplierArticle.referenceArticle", "join_articleReferenceArticle")
            ->leftJoin("join_article.emplacement", "join_articleLocation")
            ->innerJoin("inventory_entry.mission", "join_inventoryMission")
            ->leftJoin("join_preparationOrderReferenceLines.preparation", "join_preparation")
            ->leftJoin("join_preparation.statut", "join_preparationStatus")
            ->leftJoin("join_preparation.livraison", "join_deliveryOrder")
            ->leftJoin("join_deliveryOrder.statut", "join_deliveryOrderStatus")
            ->setParameter("preparationStatusToTreat", Preparation::STATUT_A_TRAITER)
            ->setParameter("preparationStatusInProgress", Preparation::STATUT_EN_COURS_DE_PREPARATION)
            ->setParameter("deliveryOrderStatusToTreat", Livraison::STATUT_A_TRAITER)
            ->setParameter("referenceStatusAvailable", ReferenceArticle::STATUT_ACTIF)
            ->setParameter("articleStatusAvailable", Article::STATUT_ACTIF)
            ->setParameter("articleStatusDispute", Article::STATUT_EN_LITIGE)
            ->andWhere("inventory_entry.anomaly = 1");

        if ($forceValidLocation) {
            $queryBuilder->andWhere("COALESCE(join_articleLocation.label, join_referenceArticleLocation.label) IS NOT NULL");
        }

        if (!empty($anomalyIds)) {
            $queryBuilder
                ->andWhere("inventory_entry.id IN (:inventoryEntries)")
                ->setParameter("inventoryEntries", $anomalyIds);
        }

        $queryBuilder = QueryBuilderHelper::setGroupBy($queryBuilder, ["isTreatable"]);

        return $queryBuilder
            ->getQuery()
            ->getResult();
    }

    /**
     * @param array|null $params
     * @param array|null $filters
     * @param bool $anomalyMode
     * @return array
     */
	public function findByParamsAndFilters(InputBag $params, $filters, $anomalyMode = false)
	{
        $countTotalResult = $this->createQueryBuilder('ie')
            ->select('COUNT(ie.id) AS count')
            ->getQuery()
            ->getResult();

        $countTotal = (!empty($countTotalResult) && !empty($countTotalResult[0]))
            ? intval($countTotalResult[0]['count'])
            : 0;

        $qb = $this->createQueryBuilder('ie');

        if ($anomalyMode) {
            $qb->where('ie.anomaly = 1');
        }

		// filtres sup
		foreach ($filters as $filter) {
			switch($filter['field']) {
				case 'emplacement':
                    $value = explode(':', $filter['value']);
					$qb
						->join('ie.location', 'l')
						->andWhere('l.label = :location')
						->setParameter('location', $value[1] ?? $filter['value']);
					break;
				case 'utilisateurs':
					$value = explode(',', $filter['value']);
					$qb
						->join('ie.operator', 'u')
						->andWhere("u.id in (:userId)")
						->setParameter('userId', $value);
					break;
				case 'dateMin':
					$qb->andWhere('ie.date >= :dateMin')
						->setParameter('dateMin', $filter['value']. " 00:00:00");
					break;
				case 'dateMax':
					$qb->andWhere('ie.date <= :dateMax')
						->setParameter('dateMax', $filter['value'] . " 23:59:59");
					break;
				case 'reference':
					$value = explode(':', $filter['value']);
					$qb
                        ->leftJoin('ie.refArticle', 'filter_reference_refArticle')
                        ->leftJoin('ie.article', 'filter_reference_article')
                        ->leftJoin('filter_reference_article.articleFournisseur', 'filter_reference_articleFournisseur')
                        ->leftJoin('filter_reference_articleFournisseur.referenceArticle', 'filter_reference_article_refArticle')
						->andWhere('filter_reference_refArticle.reference = :reference OR filter_reference_article_refArticle.reference = :reference')
						->setParameter('reference', $value[1]);
			}
		}

		//Filter search
		if (!empty($params)) {
			if (!empty($params->all('search'))) {
				$search = $params->all('search')['value'];
				if (!empty($search)) {
					$qb
						->leftJoin('ie.refArticle', 'search_referenceArticle')
						->leftJoin('ie.article', 'search_article')
						->leftJoin('search_article.articleFournisseur', 'search_articleFournisseur')
						->leftJoin('search_articleFournisseur.referenceArticle', 'search_article_referenceArticle')
						->leftJoin('ie.location', 'search_location')
						->leftJoin('ie.operator', 'search_operator')
						->andWhere('(
                            search_referenceArticle.reference LIKE :value OR
                            search_referenceArticle.libelle LIKE :value OR
                            search_referenceArticle.barCode LIKE :value OR
                            search_article.label LIKE :value OR
                            search_article.barCode LIKE :value OR
                            search_article_referenceArticle.reference LIKE :value OR
                            search_article_referenceArticle.barCode LIKE :value OR
                            search_location.label LIKE :value OR
                            search_operator.username LIKE :value
                        )')
						->setParameter('value', '%' . $search . '%');
				}
			}

			if (!empty($params->all('order')))
			{
				$order = $params->all('order')[0]['dir'];
				if (!empty($order))
				{
					$column = self::DtToDbLabels[$params->all('columns')[$params->all('order')[0]['column']]['data']];

					if ($column === 'reference') {
						$qb
                            ->leftJoin('ie.refArticle', 'order_reference_refArticle')
                            ->leftJoin('ie.article', 'order_reference_article')
                            ->leftJoin('order_reference_article.articleFournisseur', 'order_reference_articleFournisseur')
                            ->leftJoin('order_reference_articleFournisseur.referenceArticle', 'order_reference_article_refArticle')
                            ->addSelect('(CASE WHEN order_reference_refArticle.id IS NOT NULL THEN order_reference_refArticle.reference ELSE order_reference_article_refArticle.reference END) AS order_reference')
                            ->orderBy('order_reference', $order);
					}
					else if ($column === 'barCode') {
						$qb
							->leftJoin('ie.refArticle', 'order_barCode_refArticle')
							->leftJoin('ie.article', 'order_barCode_article')
                            ->addSelect('(CASE WHEN order_barCode_article.id IS NOT NULL THEN order_barCode_article.barCode ELSE order_barCode_refArticle.barCode END) AS order_barCode')
							->orderBy('order_barCode', $order);
					}
					else if ($column === 'label') {
						$qb
							->leftJoin('ie.refArticle', 'order_label_refArticle')
							->leftJoin('ie.article', 'order_label_article')
                            ->addSelect('(CASE WHEN order_label_article.id IS NOT NULL THEN order_label_article.label ELSE order_label_refArticle.libelle END) AS order_label')
							->orderBy('order_label', $order);
					}
					else if ($column === 'location') {
						$qb
							->leftJoin('ie.location', 'l3')
							->orderBy('l3.label', $order);
					}
					else if ($column === 'operator') {
						$qb
							->leftJoin('ie.operator', 'u3')
							->orderBy('u3.username', $order);
					}
					else {
						$qb
							->orderBy('ie.' . $column, $order);
					}
				}
			}
		}

		// compte éléments filtrés
		$countFiltered = QueryBuilderHelper::count($qb, 'ie');

        if ($params->getInt('start')) $qb->setFirstResult($params->getInt('start'));
        if ($params->getInt('length')) $qb->setMaxResults($params->getInt('length'));

		return [
			'data' => $qb->getQuery()->getResult(),
			'count' => $countFiltered,
			'total' => $countTotal
		];
	}


	/**
	 * @param DateTime $dateMin
	 * @param DateTime $dateMax
	 * @return InventoryEntry[]
	 */
	public function findByDates($dateMin, $dateMax)
	{
		$dateMax = $dateMax->format('Y-m-d H:i:s');
		$dateMin = $dateMin->format('Y-m-d H:i:s');

		// TODO iterate
        return $this->createQueryBuilder('entry')
            ->andWhere('entry.date BETWEEN :dateMin AND :dateMax')
            ->setParameters([
                'dateMin' => $dateMin,
                'dateMax' => $dateMax
            ])
            ->getQuery()
            ->getResult();
	}

    public function getEntryByMissionAndArticle($mission, $artId)
    {
        return $this->createQueryBuilder('entry')
            ->select('entry.date')
            ->addSelect('entry.quantity')
            ->andWhere('entry.mission = :mission')
            ->andWhere('entry.article = :art')
            ->setParameters([
                'mission' => $mission,
                'art' => $artId
            ])
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function getEntryByMissionAndRefArticle($mission, $refId)
    {
        return $this->createQueryBuilder('entry')
            ->select('entry.date')
            ->addSelect('entry.quantity')
            ->andWhere('entry.mission = :mission')
            ->andWhere('entry.refArticle = :ref')
            ->setParameters([
                'mission' => $mission,
                'ref' => $refId
            ])
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function countInventoryAnomaliesByArt($article)
    {
        return $this->createQueryBuilder('entry')
            ->select('COUNT(entry)')
            ->join('entry.article', 'article')
            ->andWhere('entry.anomaly = 1 AND article.id = :artId')
            ->setParameter('artId', $article->getId())
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countInventoryAnomaliesByRef(ReferenceArticle $ref): int
    {
        return $this->createQueryBuilder('entry')
            ->select('COUNT(entry)')
            ->join('entry.refArticle', 'refArticle')
            ->andWhere('entry.anomaly = 1 AND refArticle = :refArticle')
            ->setParameter('refArticle', $ref)
            ->getQuery()
            ->getSingleScalarResult();
    }


}
