<?php

namespace App\Repository;

use App\Entity\Article;
use App\Entity\Demande;
use App\Entity\InventoryEntry;
use App\Entity\Livraison;
use App\Entity\Preparation;
use App\Entity\ReferenceArticle;
use DateTime;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Connection;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method InventoryEntry|null find($id, $lockMode = null, $lockVersion = null)
 * @method InventoryEntry|null findOneBy(array $criteria, array $orderBy = null)
 * @method InventoryEntry[]    findAll()
 * @method InventoryEntry[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class InventoryEntryRepository extends ServiceEntityRepository
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

	public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, InventoryEntry::class);
    }

    public function countByMission($mission)
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
        /** @lang DQL */
            "SELECT COUNT(ie)
            FROM App\Entity\InventoryEntry ie
            WHERE ie.mission = :mission"
        )->setParameter('mission', $mission);

        return $query->getSingleScalarResult();
    }

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
                    ligneArticles.id IS NULL
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
            ->join('ie.refArticle', 'ra')
            ->leftJoin('ra.emplacement', 'e')
            ->leftJoin('ra.ligneArticlePreparations', 'ligneArticles')
            ->leftJoin('ligneArticles.preparation', 'preparation')
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
                        sub_preparation.id IS NULL
                        AND (
                            sub_demande.id IS NULL
                            OR sub_demandeStatus.nom = :draftRequestStatus
                        )
                        AND (
                            sub_ligneArticle.id IS NULL
                            OR (
                                sub_ligneArticle_preparation_status.nom != :preparationStatusToTreat
                                AND sub_ligneArticle_preparation_status.nom != :preparationStatusInProgress
                            )
                        )
                        AND sub_articleStatus.nom = :articleStatusAvailable
                    )
                    THEN 1 ELSE 0 END) AS sub_isTreatable
            ')
            ->join('sub_entry.article', 'sub_article')
            ->join('sub_article.statut', 'sub_articleStatus')
            ->leftJoin('sub_article.demande', 'sub_demande')
            ->leftJoin('sub_article.preparation', 'sub_preparation')
            ->leftJoin('sub_demande.statut', 'sub_demandeStatus')
            ->leftJoin('sub_article.articleFournisseur', 'sub_articleFournisseur')
            ->leftJoin('sub_articleFournisseur.referenceArticle', 'sub_referenceArticle')
            ->leftJoin('sub_referenceArticle.ligneArticlePreparations', 'sub_ligneArticle')
            ->leftJoin('sub_ligneArticle.preparation', 'sub_ligneArticle_preparation')
            ->leftJoin('sub_ligneArticle_preparation.statut', 'sub_ligneArticle_preparation_status')
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
            ->join('entry.article', 'article')
            ->leftJoin('article.emplacement', 'articleLocation')
            ->andWhere('entry.anomaly = 1')
            ->setParameter('articleStatusAvailable', Article::STATUT_ACTIF)
            ->setParameter('draftRequestStatus', Demande::STATUT_BROUILLON)
            ->setParameter('preparationStatusToTreat', Preparation::STATUT_A_TRAITER)
            ->setParameter('preparationStatusInProgress', Preparation::STATUT_EN_COURS_DE_PREPARATION);

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
	public function findByParamsAndFilters($params, $filters, $anomalyMode = false)
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
			if (!empty($params->get('search'))) {
				$search = $params->get('search')['value'];
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

			if (!empty($params->get('order')))
			{
				$order = $params->get('order')[0]['dir'];
				if (!empty($order))
				{
					$column = self::DtToDbLabels[$params->get('columns')[$params->get('order')[0]['column']]['data']];

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
		$countFiltered = count($qb->getQuery()->getResult());

		if ($params) {
			if (!empty($params->get('start'))) $qb->setFirstResult($params->get('start'));
			if (!empty($params->get('length'))) $qb->setMaxResults($params->get('length'));
		}

		$query = $qb->getQuery();

		return [
			'data' => $query ? $query->getResult() : null ,
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

		$entityManager = $this->getEntityManager();
		$query = $entityManager->createQuery(
			/** @lang DQL */
			'SELECT ie
            FROM App\Entity\InventoryEntry ie
            WHERE ie.date BETWEEN :dateMin AND :dateMax'
		)->setParameters([
			'dateMin' => $dateMin,
			'dateMax' => $dateMax
		]);
		return $query->execute();
	}


}
