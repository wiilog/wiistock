<?php

namespace App\Repository\Inventory;

use App\Entity\Article;
use App\Entity\Inventory\InventoryEntry;
use App\Entity\Livraison;
use App\Entity\PreparationOrder\Preparation;
use App\Entity\ReferenceArticle;
use App\Helper\QueryBuilderHelper;
use DateTime;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityRepository;
use Symfony\Component\HttpFoundation\InputBag;

/**
 * @method InventoryEntry|null find($id, $lockMode = null, $lockVersion = null)
 * @method InventoryEntry|null findOneBy(array $criteria, array $orderBy = null)
 * @method InventoryEntry[]    findAll()
 * @method InventoryEntry[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
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

    public function getAnomaliesOnRef(bool $forceValidLocation = false, $anomaliesIds = []) {
		$queryBuilder = $this->createQueryBuilder('ie')
            ->distinct()
            ->select('ie.id')
            ->addSelect('ra.reference')
            ->addSelect('ra.libelle as label')
            ->addSelect('e.label as location')
            ->addSelect('ra.quantiteStock as quantity')
            ->addSelect('ie.quantity as countedQuantity')
            ->addSelect('1 as is_ref')
            ->addSelect('0 as treated')
            ->addSelect('ra.barCode as barCode')
            ->addSelect('MIN(CASE WHEN (
                referenceStatus.nom = :referenceStatusAvailable
                AND (
                    preparation_order_reference_lines.id IS NULL
                    OR (
                        preparationStatus.nom != :preparationStatusToTreat
                        AND preparationStatus.nom != :preparationStatusInProgress
                        AND
                        (
                            livraison.id IS NULL
                            OR livraisonStatus.nom != :livraisonStatusToTreat
                        )
                    )
                )
            ) THEN 1 ELSE 0 END) AS isTreatable')
            ->addSelect('mission.id as mission_id')
            ->addSelect('mission.startPrevDate AS mission_start')
            ->addSelect('mission.endPrevDate AS mission_end')
            ->addSelect('mission.name AS mission_name')
            ->join('ie.refArticle', 'ra')
            ->leftJoin('ie.mission', 'mission')
            ->leftJoin('ra.emplacement', 'e')
            ->leftJoin('ra.preparationOrderReferenceLines', 'preparation_order_reference_lines')
            ->leftJoin('preparation_order_reference_lines.preparation', 'preparation')
            ->leftJoin('preparation.statut', 'preparationStatus')
            ->leftJoin('preparation.livraison', 'livraison')
            ->leftJoin('livraison.statut', 'livraisonStatus')
            ->leftJoin('ra.statut', 'referenceStatus')
            ->groupBy('ie.id')
            ->addGroupBy('ra.reference')
            ->addGroupBy('label')
            ->addGroupBy('location')
            ->addGroupBy('quantity')
            ->addGroupBy('countedQuantity')
            ->addGroupBy('is_ref')
            ->addGroupBy('treated')
            ->addGroupBy('barCode')
            ->setParameter('preparationStatusToTreat', Preparation::STATUT_A_TRAITER)
            ->setParameter('preparationStatusInProgress', Preparation::STATUT_EN_COURS_DE_PREPARATION)
            ->setParameter('livraisonStatusToTreat', Livraison::STATUT_A_TRAITER)
            ->setParameter('referenceStatusAvailable', ReferenceArticle::STATUT_ACTIF)
            ->andWhere('ie.anomaly = 1');

		if ($forceValidLocation) {
            $queryBuilder->andWhere('e IS NOT NULL');
        }

        if (!empty($anomaliesIds)) {
            $queryBuilder
                ->andWhere('ie.id IN (:inventoryEntries)')
                ->setParameter("inventoryEntries", $anomaliesIds, Connection::PARAM_STR_ARRAY);
        }

		return $queryBuilder
            ->getQuery()
            ->getArrayResult();
	}

    public function getAnomaliesOnArt(bool $forceValidLocation = false, $anomaliesIds = []) {
        $subQueryBuilder = $this->createQueryBuilder('sub_entry')
            ->select('
                MIN(CASE WHEN
                    (
                        sub_articleStatus.nom = :articleStatusAvailable
                        OR sub_articleStatus.nom = :articleStatusDispute
                    )
                    THEN 1 ELSE 0 END) AS sub_isTreatable
            ')
            ->join('sub_entry.article', 'sub_article')
            ->join('sub_article.statut', 'sub_articleStatus')
            ->where('sub_entry.id = entry.id')
            ->groupBy('sub_entry.id');

        $isTreatableDQL = $subQueryBuilder
            ->getQuery()
            ->getDQL();

        $queryBuilder = $this->createQueryBuilder('entry')
            ->select('entry.id')
            ->addSelect('article.reference')
            ->addSelect('article.label')
            ->addSelect('articleLocation.label as location')
            ->addSelect('article.quantite as quantity')
            ->addSelect('0 as is_ref')
            ->addSelect('0 as treated')
            ->addSelect('article.barCode as barCode')
            ->addSelect("($isTreatableDQL) AS isTreatable")
            ->addSelect('mission.id as mission_id')
            ->addSelect('mission.startPrevDate AS mission_start')
            ->addSelect('mission.endPrevDate AS mission_end')
            ->addSelect('mission.name AS mission_name')
            ->join('entry.article', 'article')
            ->leftJoin('article.emplacement', 'articleLocation')
            ->leftJoin('entry.mission', 'mission')
            ->andWhere('entry.anomaly = 1')
            ->setParameter('articleStatusAvailable', Article::STATUT_ACTIF)
            ->setParameter('articleStatusDispute', Article::STATUT_EN_LITIGE);

        if ($forceValidLocation) {
            $queryBuilder->andWhere('articleLocation IS NOT NULL');
        }

        if (!empty($anomaliesIds)) {
            $queryBuilder
                ->andWhere('entry.id IN (:inventoryEntries)')
                ->setParameter("inventoryEntries", $anomaliesIds, Connection::PARAM_STR_ARRAY);
        }

        return $queryBuilder
            ->getQuery()
            ->getScalarResult();
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
