<?php

namespace App\Repository;

use App\Entity\Article;
use App\Entity\DeliveryRequest\Demande;
use App\Entity\Emplacement;
use App\Entity\FreeField;
use App\Entity\InventoryFrequency;
use App\Entity\InventoryMission;
use App\Entity\IOT\Sensor;
use App\Entity\OrdreCollecte;
use App\Entity\PreparationOrder\Preparation;
use App\Entity\ReferenceArticle;
use App\Entity\TransferRequest;
use App\Entity\Utilisateur;

use App\Entity\VisibilityGroup;
use App\Helper\QueryCounter;
use Symfony\Component\HttpFoundation\InputBag;
use WiiCommon\Helper\Stream;
use App\Service\VisibleColumnService;
use DateTime;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityRepository;

use Doctrine\ORM\QueryBuilder;
use WiiCommon\Helper\StringHelper;

/**
 * @method Article|null find($id, $lockMode = null, $lockVersion = null)
 * @method Article|null findOneBy(array $criteria, array $orderBy = null)
 * @method Article[]    findAll()
 * @method Article[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ArticleRepository extends EntityRepository {

    private const FIELD_ENTITY_NAME = [
        "location" => "emplacement",
        "unitPrice" => "prixUnitaire",
        "quantity" => "quantite"
    ];

    private const FIELDS_TYPE_DATE = [
        "dateLastInventory",
        "expiryDate",
        "stockEntryDate"
    ];

    public function findExpiredToGenerate($delay = 0) {
        $since = new DateTime("now");
        $since->modify("+{$delay}day");

        return $this->createQueryBuilder("a")
            ->join('a.statut','status')
            ->where("a.expiryDate <= :since")
            ->andWhere("status.code != :consumed")
            ->setParameter("since", $since)
            ->setParameter('consumed', Article::STATUT_INACTIF)
            ->getQuery()
            ->getResult();
    }

    public function getReferencesByRefAndDate($refPrefix, $date)
	{
		$entityManager = $this->getEntityManager();
		$query = $entityManager->createQuery(
			'SELECT a.reference
            FROM App\Entity\Article a
            WHERE a.reference LIKE :refPrefix'
		)->setParameter('refPrefix', $refPrefix . $date . '%');

		return array_column($query->execute(), 'reference');
	}

    public function setNullByReception($id)
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            '
            UPDATE App\Entity\Article a
            SET a.receptionReferenceArticle = null
            WHERE a.receptionReferenceArticle = :id'
        )->setParameter('id', $id);
        return $query->execute();
    }

    public function findByCollecteId($id)
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "SELECT a
             FROM App\Entity\Article a
             JOIN a.collectes c
             WHERE c.id = :id
            "
        )->setParameter('id', $id);
        return $query->getResult();
    }

    public function iterateAll(Utilisateur $user): iterable {
        $queryBuilder = $this->createQueryBuilder('article');
        $visibilityGroup = $user->getVisibilityGroups();
        if (!$visibilityGroup->isEmpty()) {
            $queryBuilder
                ->join('article.articleFournisseur', 'join_supplierArticle')
                ->join('join_supplierArticle.referenceArticle', 'join_referenceArticle')
                ->join('join_referenceArticle.visibilityGroup', 'visibility_group')
                ->andWhere('visibility_group.id IN (:userVisibilityGroups)')
                ->setParameter('userVisibilityGroups', Stream::from(
                    $visibilityGroup->toArray()
                )->map(fn(VisibilityGroup $visibilityGroup) => $visibilityGroup->getId())->toArray());
        }

        return $queryBuilder->distinct()
            ->select('referenceArticle.reference')
            ->addSelect('article.label')
            ->addSelect('article.quantite')
            ->addSelect('type.label as typeLabel')
            ->addSelect('statut.nom as statutName')
            ->addSelect('article.commentaire')
            ->addSelect('emplacement.label as empLabel')
            ->addSelect('article.barCode')
            ->addSelect('article.dateLastInventory')
            ->addSelect('article.freeFields')
            ->addSelect('article.batch')
            ->addSelect('article.stockEntryDate')
            ->addSelect('article.expiryDate')
            ->addSelect("join_visibilityGroup.label AS visibilityGroup")
            ->leftJoin('article.articleFournisseur', 'articleFournisseur')
            ->leftJoin('article.emplacement', 'emplacement')
            ->leftJoin('article.type', 'type')
            ->leftJoin('article.statut', 'statut')
            ->leftJoin('articleFournisseur.referenceArticle', 'referenceArticle')
            ->leftJoin('referenceArticle.visibilityGroup', 'join_visibilityGroup')
            ->groupBy('article.id')
            ->getQuery()
            ->toIterable();
    }

	public function getIdAndRefBySearch($search,
                                        $activeOnly = false,
                                        $field = 'reference',
                                        $referenceArticleReference = null,
                                        $activeReferenceOnly = false,
                                        Utilisateur $user)
	{
        $statusNames = [
            Article::STATUT_ACTIF,
            Article::STATUT_EN_LITIGE
        ];

        $queryBuilder = $this->createQueryBuilder('article');
        $visibilityGroup = $user->getVisibilityGroups();
        if (!$visibilityGroup->isEmpty()) {
            $queryBuilder
                ->join('article.articleFournisseur', 'join_supplierArticle')
                ->join('join_supplierArticle.referenceArticle', 'join_referenceArticle')
                ->join('join_referenceArticle.visibilityGroup', 'visibility_group')
                ->andWhere('visibility_group.id IN (:userVisibilityGroups)')
                ->setParameter('userVisibilityGroups', Stream::from(
                    $visibilityGroup->toArray()
                )->map(fn(VisibilityGroup $visibilityGroup) => $visibilityGroup->getId())->toArray());
        }

        $queryBuilder
            ->select('article.id AS id')
            ->addSelect("article.${field} AS text")
            ->addSelect('location.label AS locationLabel')
            ->addSelect('article.quantite AS quantity')
            ->leftJoin('article.emplacement', 'location')
            ->andWhere("article.${field} LIKE :search")
            ->setParameter('search', '%' . $search . '%');

        if ($activeOnly) {
            $queryBuilder
                ->join('article.statut', 'status');

            $exprBuilder = $queryBuilder->expr();
            $OROperands = [];
            foreach ($statusNames as $index => $statusName) {
                $OROperands[] = "status.nom = :articleStatusName$index";
                $queryBuilder->setParameter("articleStatusName$index", $statusName);
            }
            $queryBuilder->andWhere('(' . $exprBuilder->orX(...$OROperands) . ')');
        }

        if ($referenceArticleReference) {
            $queryBuilder
                ->join('article.articleFournisseur', 'articleFournisseur')
                ->join('articleFournisseur.referenceArticle', 'referenceArticle')
                ->andWhere('referenceArticle.reference = :referenceArticleReference')
                ->setParameter('referenceArticleReference', $referenceArticleReference);
        }

        if ($activeReferenceOnly) {
            $queryBuilder
                ->join('article.articleFournisseur', 'activeReference_articleFournisseur')
                ->join('activeReference_articleFournisseur.referenceArticle', 'activeReference_referenceArticle')
                ->join('activeReference_referenceArticle.statut', 'activeReference_status')
                ->andWhere('activeReference_status.nom = :activeReference_statusName')
                ->setParameter('activeReference_statusName', ReferenceArticle::STATUT_ACTIF);
        }

		return $queryBuilder
            ->getQuery()
            ->execute();
	}

	public function findByRefArticleAndStatut($refArticle, array $statusNames, string $refArticleStatusName = null)
	{

		$queryBuilder = $this->createQueryBuilder('article')
            ->select('article')
            ->join('article.articleFournisseur', 'articleFournisseur')
            ->join('articleFournisseur.referenceArticle', 'referenceArticle')
            ->where('referenceArticle = :refArticle')
            ->setParameter('refArticle', $refArticle);

		if(!empty($statusNames)) {
            $queryBuilder->join('article.statut', 'article_status');
            $exprBuilder = $queryBuilder->expr();
            $OROperands = [];

            foreach ($statusNames as $index => $statusName) {
                $OROperands[] = "article_status.nom = :articleStatusName$index";
                $queryBuilder->setParameter("articleStatusName$index", $statusName);
            }
            $queryBuilder->andWhere('(' . $exprBuilder->orX(...$OROperands) . ')');
        }

		if ($refArticleStatusName) {
            $queryBuilder
                ->join('referenceArticle.statut', 'referenceArticle_status')
                ->andWhere('referenceArticle_status.nom = :referenceArticle_statusName')
                ->setParameter('referenceArticle_statusName', $refArticleStatusName);
        }

		return $queryBuilder->getQuery()->execute();
	}

	public function findActiveArticles(ReferenceArticle $referenceArticle): array
	{
	    return $this->createQueryBuilder('article')
            ->join('article.articleFournisseur', 'articleFournisseur')
            ->join('articleFournisseur.referenceArticle', 'referenceArticle')
            ->join('article.statut', 'articleStatus')
            ->where('articleStatus.nom = :activeStatus')
            ->andWhere('article.quantite IS NOT NULL')
            ->andWhere('article.quantite > 0')
            ->andWhere('referenceArticle = :refArticle')
            ->setParameter('refArticle', $referenceArticle)
            ->setParameter('activeStatus', Article::STATUT_ACTIF)
            ->getQuery()
            ->getResult();
	}

    public function findByParamsAndFilters(InputBag $params, $filters, Utilisateur $user)
    {
        $queryBuilder = $this->createQueryBuilder("a");

        $visibilityGroup = $user->getVisibilityGroups();
        if (!$visibilityGroup->isEmpty()) {
            $queryBuilder
                ->join('a.articleFournisseur', 'join_supplierArticle')
                ->join('join_supplierArticle.referenceArticle', 'join_referenceArticle')
                ->join('join_referenceArticle.visibilityGroup', 'visibility_group')
                ->andWhere('visibility_group.id IN (:userVisibilityGroups)')
                ->setParameter('userVisibilityGroups', Stream::from(
                    $visibilityGroup->toArray()
                )->map(fn(VisibilityGroup $visibilityGroup) => $visibilityGroup->getId())->toArray());
        }

        $countQuery = $countTotal = QueryCounter::count($queryBuilder, 'a');

		// filtres sup
		foreach ($filters as $filter) {
			switch ($filter['field']) {
				case 'statut':
					$value = explode(',', $filter['value']);
					$queryBuilder
						->join('a.statut', 's_filter')
						->andWhere('s_filter.nom IN (:statut)')
						->setParameter('statut', $value);
					break;
			}
		}

		// prise en compte des paramètres issus du datatable
        if (!empty($params)) {
            if (!empty($params->get('search'))) {
                $searchValue = $params->get('search')['value'];

                if (!empty($searchValue)) {
                    $search = "%$searchValue%";

                    $ids = [];
                    $query = [];

                    // valeur par défaut si aucune valeur enregistrée pour cet utilisateur
					$searchForArticle = $user->getRechercheForArticle();
					if (empty($searchForArticle)) {
						$searchForArticle = Utilisateur::SEARCH_DEFAULT;
					}

                    foreach ($searchForArticle as $key => $searchField) {

                        $date = DateTime::createFromFormat('d/m/Y', $searchValue);
                        $date = $date ? $date->format('Y-m-d') : null;
                        switch ($searchField) {
                            case "type":
                                $subqb = $this->createQueryBuilder("a")
                                    ->select('a.id')
                                    ->leftJoin('a.type', 't_search')
                                    ->andWhere('t_search.label LIKE :search')
                                    ->setParameter('search', $search);

                                foreach ($subqb->getQuery()->execute() as $idArray) {
                                    $ids[] = $idArray['id'];
                                }
                                break;

                            case "status":
                                $subqb = $this->createQueryBuilder("a")
                                    ->select('a.id')
                                    ->leftJoin('a.statut', 's_search')
                                    ->andWhere('s_search.nom LIKE :search')
                                    ->setParameter('search', $search);

                                foreach ($subqb->getQuery()->execute() as $idArray) {
                                    $ids[] = $idArray['id'];
                                }
                                break;
                            case "location":
                                $subqb = $this->createQueryBuilder("a")
                                    ->select('a.id')
                                    ->leftJoin('a.emplacement', 'e_search')
                                    ->andWhere('e_search.label LIKE :search')
                                    ->setParameter('search', $search);

                                foreach ($subqb->getQuery()->execute() as $idArray) {
                                    $ids[] = $idArray['id'];
                                }
                                break;
                            case "articleReference":
                            case "reference":
                                $subqb = $this->createQueryBuilder("a")
                                    ->select('a.id')
                                    ->leftJoin('a.articleFournisseur', 'afa')
                                    ->leftJoin('afa.referenceArticle', 'ra')
                                    ->andWhere('ra.reference LIKE :search')
                                    ->setParameter('search', $search);

                                foreach ($subqb->getQuery()->execute() as $idArray) {
                                    $ids[] = $idArray['id'];
                                }
                                break;
                            case "supplierReference":
                                $subqb = $this->createQueryBuilder("a")
                                    ->select('a.id')
                                    ->leftJoin('a.articleFournisseur', 'afa')
                                    ->andWhere('afa.reference LIKE :search')
                                    ->setParameter('search', $search);

                                foreach ($subqb->getQuery()->execute() as $idArray) {
                                    $ids[] = $idArray['id'];
                                }
                                break;
                            default:
                                $field = self::FIELD_ENTITY_NAME[$searchField] ?? $searchField;
                                $freeFieldId = VisibleColumnService::extractFreeFieldId($field);
                                if(is_numeric($freeFieldId)) {
                                    $query[] = "JSON_SEARCH(LOWER(a.freeFields), 'one', :search, NULL, '$.\"$freeFieldId\"') IS NOT NULL";
                                    $queryBuilder->setParameter("search", $date ?: strtolower($search));
                                } else if (property_exists(Article::class, $field)) {
                                    if ($date && in_array($field, self::FIELDS_TYPE_DATE)) {
                                        $query[] = "a.$field BETWEEN :dateMin AND :dateMax";
                                        $queryBuilder
                                            ->setParameter('dateMin' , $date . ' 00:00:00')
                                            ->setParameter('dateMax' , $date . ' 23:59:59');
                                    } else {
                                        $query[] = "a.$field LIKE :search";
                                        $queryBuilder->setParameter('search', $search);
                                    }
                                }
                                break;
                        }
                    }

                    // si le résultat de la recherche est vide on renvoie []
                    if (empty($ids)) {
                        $ids = [0];
                    }

                    foreach ($ids as $id) {
                        $query[] = 'a.id  = ' . $id;
                    }

                    if (!empty($query)) {
                        $queryBuilder->andWhere(implode(' OR ', $query));
                    }
                }

				$countQuery =  QueryCounter::count($queryBuilder, 'a');
			}

            if (!empty($params->get('order'))) {
                $order = $params->get('order')[0]['dir'];
                if (!empty($order)) {
                    $column = $params->get('columns')[$params->get('order')[0]['column']]['data'];

                    switch ($column) {
                        case "type":
                            $queryBuilder->leftJoin('a.type', 't')
                                ->orderBy('t.label', $order);
                            break;
                        case "supplierReference":
                            $queryBuilder->leftJoin('a.articleFournisseur', 'af1')
                                ->orderBy('af1.reference', $order);
                            break;
                        case "location":
                            $queryBuilder->leftJoin('a.emplacement', 'e')
                                ->orderBy('e.label', $order);
                            break;
                        case "reference":
                            $queryBuilder->leftJoin('a.articleFournisseur', 'af2')
                                ->leftJoin('af2.referenceArticle', 'ra2')
                                ->orderBy('ra2.reference', $order);
                            break;
                        case "status":
                            $queryBuilder->leftJoin('a.statut', 's_sort')
                                ->orderBy('s_sort.nom', $order);
                            break;
                        case "pairing":
                            $queryBuilder->leftJoin('a.pairings', 'order_pairings')
                                ->orderBy('order_pairings.active', $order);
                            break;
                        default:
                            $field = self::FIELD_ENTITY_NAME[$column] ?? $column;
                            $freeFieldId = VisibleColumnService::extractFreeFieldId($column);

                            if(is_numeric($freeFieldId)) {
                                /** @var FreeField $freeField */
                                $freeField = $this->getEntityManager()->getRepository(FreeField::class)->find($freeFieldId);
                                if($freeField->getTypage() === FreeField::TYPE_NUMBER) {
                                    $queryBuilder->orderBy("CAST(JSON_EXTRACT(a.freeFields, '$.\"$freeFieldId\"') AS SIGNED)", $order);
                                } else {
                                    $queryBuilder->orderBy("JSON_EXTRACT(a.freeFields, '$.\"$freeFieldId\"')", $order);
                                }
                            } else if (property_exists(Article::class, $field)) {
                                $queryBuilder->orderBy("a.$field", $order);
                            }
                            break;
                    }
                }
            }

            if ($params->getInt('start')) $queryBuilder->setFirstResult($params->getInt('start'));
            if ($params->getInt('length')) $queryBuilder->setMaxResults($params->getInt('length'));
        }
        $query = $queryBuilder->getQuery();

        return [
            'data' => $query ? $query->getResult() : null,
            'count' => $countQuery,
            'total' => $countTotal
        ];
    }

    public function countActiveArticles()
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
        	/** @lang DQL */
            "SELECT COUNT(a)
            FROM App\Entity\Article a
            JOIN a.statut s
            WHERE s.nom = :active"
		)->setParameter('active', Article::STATUT_ACTIF);

        return $query->getSingleScalarResult();
    }

    public function countAll()
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "SELECT COUNT(a)
            FROM App\Entity\Article a"
		);

        return $query->getSingleScalarResult();
    }

    public function getByPreparationsIds($preparationsIds): array
    {
        return $this->createQueryBuilder('article')
            ->select('article.reference AS reference')
            ->addSelect('join_location.label AS location')
            ->addSelect('article.label AS label')
            ->addSelect('join_preparationLine.quantityToPick AS quantity')
            ->addSelect('0 AS is_ref')
            ->addSelect('join_preparation.id AS id_prepa')
            ->addSelect('article.barCode AS barCode')
            ->addSelect('join_referenceArticle.reference AS reference_article_reference')
            ->leftJoin('article.emplacement', 'join_location')
            ->join('article.preparationOrderLines', 'join_preparationLine')
            ->join('join_preparationLine.preparation', 'join_preparation')
            ->join('article.articleFournisseur', 'join_supplierArticle')
            ->join('join_supplierArticle.referenceArticle', 'join_referenceArticle')
            ->andWhere('join_preparation.id IN (:preparationsIds)')
            ->andWhere('article.quantite > 0')
            ->setParameter('preparationsIds', $preparationsIds, Connection::PARAM_STR_ARRAY)
            ->getQuery()
            ->getResult();
	}

    public function getArticlePrepaForPickingByUser($user, array $preparationIdsFilter = []) {
        $queryBuilder = $this->createQueryBuilder('article')
            ->select('DISTINCT article.reference AS reference')
            ->addSelect('article.label AS label')
            ->addSelect('emplacement.label AS location')
            ->addSelect('article.quantite AS quantity')
            ->addSelect('referenceArticle.reference AS reference_article')
            ->addSelect('article.barCode AS barCode')
            ->addSelect('referenceArticle.stockManagement AS management')
            ->addSelect("
                (CASE
                    WHEN (referenceArticle.stockManagement = :fefoStockManagement AND article.expiryDate IS NOT NULL) THEN DATE_FORMAT(article.expiryDate, '%d/%m/%Y')
                    WHEN (referenceArticle.stockManagement = :fifoStockManagement AND article.stockEntryDate IS NOT NULL) THEN DATE_FORMAT(article.stockEntryDate, '%d/%m/%Y %T')
                    ELSE :null
                END) AS management_date
            ")
            ->addSelect('
                (CASE
                    WHEN (referenceArticle.stockManagement = :fefoStockManagement AND article.expiryDate IS NOT NULL) THEN UNIX_TIMESTAMP(article.expiryDate)
                    WHEN (referenceArticle.stockManagement = :fifoStockManagement AND article.stockEntryDate IS NOT NULL) THEN UNIX_TIMESTAMP(article.stockEntryDate)
                    ELSE :null
                END) AS management_order
            ')
            ->join('article.articleFournisseur', 'articleFournisseur')
            ->join('articleFournisseur.referenceArticle', 'referenceArticle')
            ->join('article.statut', 'articleStatut')
            ->leftJoin('article.emplacement', 'emplacement')
            ->join('referenceArticle.preparationOrderReferenceLines', 'preparationOrderReferenceLines')
            ->join('preparationOrderReferenceLines.preparation', 'preparation')
            ->join('preparation.statut', 'statutPreparation')
            ->andWhere('(statutPreparation.nom = :preparationToTreat OR (statutPreparation.nom = :preparationInProgress AND preparation.utilisateur = :preparationOperator))')
            ->andWhere('articleStatut.nom = :articleActif')
            ->andWhere('article.quantite IS NOT NULL')
            ->andWhere('article.quantite > 0')
            ->setParameter('articleActif', Article::STATUT_ACTIF)
            ->setParameter('preparationToTreat', Preparation::STATUT_A_TRAITER)
            ->setParameter('preparationInProgress', Preparation::STATUT_EN_COURS_DE_PREPARATION)
            ->setParameter('preparationOperator', $user)
            ->setParameter('fifoStockManagement', ReferenceArticle::STOCK_MANAGEMENT_FIFO)
            ->setParameter('fefoStockManagement', ReferenceArticle::STOCK_MANAGEMENT_FEFO)
            ->setParameter('null', null);

        if (!empty($preparationIdsFilter)) {
            $queryBuilder
                ->andWhere('preparation.id IN (:preparationIdsFilter)')
                ->setParameter('preparationIdsFilter', $preparationIdsFilter, Connection::PARAM_STR_ARRAY);
        }

        return $queryBuilder
            ->getQuery()
            ->execute();
    }

    public function getByLivraisonsIds($livraisonsIds)
    {
        return $this->createQueryBuilder('article')
            ->select('article.reference AS reference')
            ->addSelect('join_location.label AS location')
            ->addSelect('article.label AS label')
            ->addSelect('join_preparationOrderLines.quantityToPick AS quantity')
            ->addSelect('0 as is_ref')
            ->addSelect('join_delivery.id AS id_livraison')
            ->addSelect('article.barCode AS barCode')
            ->leftJoin('article.emplacement', 'join_location')
            ->join('article.preparationOrderLines', 'join_preparationOrderLines')
            ->join('join_preparationOrderLines.preparation', 'join_preparation')
            ->join('join_preparation.livraison', 'join_delivery')
            ->andWhere('join_delivery.id IN (:deliveryIds)')
            ->andWhere('article.quantite > 0')
            ->setParameter('deliveryIds', $livraisonsIds, Connection::PARAM_STR_ARRAY)
            ->getQuery()
            ->execute();
	}

	public function getByOrdreCollectesIds($collectesIds)
	{
		$em = $this->getEntityManager();
		//TODO patch temporaire CEA (sur quantité envoyée)
		$query = $em
			->createQuery($this->getArticleCollecteQuery() . " WHERE oc.id IN (:collectesIds)")
            ->setParameter('collectesIds', $collectesIds, Connection::PARAM_STR_ARRAY);

		return $query->execute();
	}

	public function getByTransferOrders(array $transfersOrders): array {
	    if (!empty($transfersOrders)) {
            $res = $this->createQueryBuilder('article')
                ->select('article.barCode AS barcode')
                ->addSelect('referenceArticle.libelle AS label')
                ->addSelect('referenceArticle.reference AS reference')
                ->addSelect('referenceArticle_location.label AS location')
                ->addSelect('article.quantite AS quantity')
                ->addSelect('transferOrder.id AS transfer_order_id')
                ->join('article.transferRequests', 'transferRequest')
                ->join('transferRequest.order', 'transferOrder')
                ->join('article.articleFournisseur', 'articleFournisseur')
                ->join('articleFournisseur.referenceArticle', 'referenceArticle')
                ->leftJoin('referenceArticle.emplacement', 'referenceArticle_location')
                ->where('transferOrder IN (:transferOrders)')
                ->setParameter('transferOrders', $transfersOrders)
                ->getQuery()
                ->getResult();
        }
	    else {
            $res = [];
        }
		return $res;
	}

	public function getByOrdreCollecteId($collecteId)
	{
		$em = $this->getEntityManager();
		$query = $em
			->createQuery($this->getArticleCollecteQuery() . " WHERE oc.id = :id")
			->setParameter('id', $collecteId);

		return $query->execute();
	}

	private function getArticleCollecteQuery()
	{
		return (/** @lang DQL */
		"SELECT ra.reference,
			 e.label as location,
			 a.label,
			 a.quantite as quantity,
			 0 as is_ref, oc.id as id_collecte,
			 a.barCode,
			 ra.libelle as reference_label
			FROM App\Entity\Article a
			JOIN a.articleFournisseur artf
			JOIN artf.referenceArticle ra
			LEFT JOIN a.emplacement e
			JOIN a.ordreCollecte oc
			LEFT JOIN oc.statut s"
		);
	}

    public function findOneByReference($reference)
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
        /** @lang DQL */
            "SELECT a
			FROM App\Entity\Article a
			WHERE a.reference = :reference"
		)->setParameter('reference', $reference);

		return $query->getOneOrNullResult();
	}

    public function countByEmplacement($emplacementId)
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
        /** @lang DQL */
            "SELECT COUNT(a)
			FROM App\Entity\Article a
			JOIN a.emplacement e
			WHERE e.id = :emplacementId"
        )->setParameter('emplacementId', $emplacementId);

        return $query->getSingleScalarResult();
    }

    public function getEntryByMission($mission, $artId)
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
        /** @lang DQL */
            "SELECT e.date, e.quantity
            FROM App\Entity\InventoryEntry e
            WHERE e.mission = :mission AND e.article = :art"
        )->setParameters([
            'mission' => $mission,
            'art' => $artId
        ]);
        return $query->getOneOrNullResult();
    }

    public function countByMission($mission)
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
        /** @lang DQL */
            "SELECT COUNT(a)
            FROM App\Entity\InventoryMission im
            LEFT JOIN im.articles a
            WHERE im = :mission"
        )->setParameter('mission', $mission);

        return $query->getSingleScalarResult();
    }

    public function findActiveByFrequencyWithoutDateInventoryOrderedByEmplacementLimited($frequency, $limit)
    {

        $queryBuilder = $this->createQueryBuilder('article');
        $exprBuilder = $queryBuilder->expr();
        $queryBuilder
            ->select('article')
            ->join('article.articleFournisseur', 'articleFournisseur')
            ->join('articleFournisseur.referenceArticle', 'referenceArticle')
            ->join('referenceArticle.category', 'category')
            ->join('referenceArticle.statut', 'referenceArticle_status')
            ->leftJoin('article.statut', 'article_status')
            ->leftJoin('article.emplacement', 'article_location')
            ->where('category.frequency = :frequency')
            ->andWhere('referenceArticle.typeQuantite = :typeQuantity')
            ->andWhere('article.dateLastInventory IS NULL')
            ->andWhere('(' . $exprBuilder->orX('article_status.nom = :activeStatus', 'article_status.nom = :disputeStatus') . ')')
            ->andWhere('referenceArticle_status.nom = :referenceActiveStatus')
            ->orderBy('article_location.label')
            ->setParameters([
                'frequency' => $frequency,
                'typeQuantity' => ReferenceArticle::TYPE_QUANTITE_ARTICLE,
                'activeStatus' => Article::STATUT_ACTIF,
                'disputeStatus' => Article::STATUT_EN_LITIGE,
                'referenceActiveStatus' => ReferenceArticle::STATUT_ACTIF
            ]);

        if ($limit) {
            $queryBuilder->setMaxResults((int) $limit);
        }

        return $queryBuilder
            ->getQuery()
            ->getResult();
    }

	public function getHighestBarCodeByDateCode($dateCode)
	{
		$em = $this->getEntityManager();
		$query = $em->createQuery(
		/** @lang DQL */
		"SELECT a.barCode
		FROM App\Entity\Article a
		WHERE a.barCode LIKE :barCode
		ORDER BY a.barCode DESC
		")
            ->setParameter('barCode', Article::BARCODE_PREFIX . $dateCode . '%')
            ->setMaxResults(1);

        $result = $query->execute();
        return $result ? $result[0]['barCode'] : null;
    }

    public function countInventoryAnomaliesByArt($article)
    {
        $em = $this->getEntityManager();

        $query = $em->createQuery(
        /** @lang DQL */
            "SELECT COUNT(ie)
			FROM App\Entity\InventoryEntry ie
			JOIN ie.article a
			WHERE ie.anomaly = 1 AND a.id = :artId
			")->setParameter('artId', $article->getId());

        return $query->getSingleScalarResult();
    }

	public function findArticleByBarCodeAndLocation(string $barCode, string $location) {
        $queryBuilder = $this
            ->createQueryBuilderByBarCodeAndLocation($barCode, $location)
            ->addSelect('article');

        return $queryBuilder->getQuery()->execute();
    }

	public function getOneArticleByBarCodeAndLocation(string $barCode, string $location) {
        $queryBuilder = $this
            ->createQueryBuilderByBarCodeAndLocation($barCode, $location)
            ->select('article.barCode as barCode')
            ->select('article.id as id')
            ->addSelect('article.quantite as quantity')
            ->addSelect('referenceArticle_status.nom as reference_status')
            ->addSelect('0 as is_ref')
            ->join('article.articleFournisseur', 'article_articleFournisseur')
            ->join('article_articleFournisseur.referenceArticle', 'articleFournisseur_reference')
            ->join('articleFournisseur_reference.statut', 'referenceArticle_status');

        $result = $queryBuilder->getQuery()->execute();
        return !empty($result) ? $result[0] : null;
    }

    private function createQueryBuilderByBarCodeAndLocation(string $barCode, string $location): QueryBuilder {
        $queryBuilder = $this->createQueryBuilder('article');
        $exprBuilder = $queryBuilder->expr();
        $queryBuilder
            ->join('article.emplacement', 'emplacement')
            ->join('article.statut', 'status')
            ->andWhere('emplacement.label = :location')
            ->andWhere('article.barCode = :barCode')
            ->andWhere($exprBuilder->orX(
                'status.nom = :activeStatusName',
                'status.nom = :statusDisputeName'
            ))
            ->setParameter('location', $location)
            ->setParameter('barCode', $barCode)
            ->setParameter('activeStatusName', Article::STATUT_ACTIF)
            ->setParameter('statusDisputeName', Article::STATUT_EN_LITIGE);

        return $queryBuilder;
    }

    public function findActiveOrDisputeForReference($reference, Emplacement $emplacement) {
        return $this->createQueryBuilder("a")
            ->join("a.articleFournisseur", "af")
            ->leftJoin("a.statut", "articleStatut")
            ->where("articleStatut.nom IN (:statuses)")
            ->andWhere("af.referenceArticle = :reference")
            ->andWhere('a.emplacement = :location')
            ->setParameter("reference", $reference)
            ->setParameter("location", $emplacement)
            ->setParameter("statuses", [Article::STATUT_ACTIF, Article::STATUT_EN_LITIGE])
            ->getQuery()
            ->getResult();
    }

    public function getArticlesGroupedByTransfer(array $requests, bool $isRequests = true) {
        if(!empty($requests)) {
            $queryBuilder = $this->createQueryBuilder('article')
                ->select('article.barCode AS barCode')
                ->addSelect('referenceArticle.reference AS reference')
                ->join('article.articleFournisseur', 'articleFournisseur')
                ->join('articleFournisseur.referenceArticle', 'referenceArticle')
                ->join('article.transferRequests', 'transferRequest');

            if ($isRequests) {
                $queryBuilder
                    ->addSelect('transferRequest.id AS transferId')
                    ->where('transferRequest.id IN (:requests)')
                    ->setParameter('requests', $requests);
            }
            else {
                $queryBuilder
                    ->addSelect('transferOrder.id AS transferId')
                    ->join('transferRequest.order', 'transferOrder')
                    ->where('transferOrder.id IN (:orders)')
                    ->setParameter('orders', $requests);
            }

            $res = $queryBuilder
                ->getQuery()
                ->getResult();

            return Stream::from($res)
                ->reduce(function (array $acc, array $articleArray) {
                    $transferRequestId = $articleArray['transferId'];
                    if (!isset($acc[$transferRequestId])) {
                        $acc[$transferRequestId] = [];
                    }
                    $acc[$transferRequestId][] = $articleArray;
                    return $acc;
                }, []);
        }
        else {
            return [];
        }
    }
    public function findWithNoPairing(?string $term) {
        return $this->createQueryBuilder("article")
            ->select("article.id AS id, article.barCode AS text")
            ->leftJoin("article.pairings", "pairings")
            ->where("pairings.article is null OR pairings.active = 0")
            ->andWhere("article.barCode LIKE :term")
            ->setParameter("term", "%$term%")
            ->setMaxResults(100)
            ->getQuery()
            ->getArrayResult();
    }

    private function createSensorPairingDataQueryUnion(Article $article): string {
        $createQueryBuilder = function () {
            return $this->createQueryBuilder('article')
                ->select('pairing.id AS pairingId')
                ->addSelect('sensorWrapper.name AS name')
                ->addSelect('(CASE WHEN sensorWrapper.deleted = false AND pairing.active = true AND (pairing.end IS NULL OR pairing.end > NOW()) THEN 1 ELSE 0 END) AS active')
                ->addSelect('article.barCode AS entity')
                ->addSelect("'" . Sensor::ARTICLE . "' AS entityType")
                ->addSelect('article.id AS entityId')
                ->join('article.pairings', 'pairing')
                ->join('pairing.sensorWrapper', 'sensorWrapper')
                ->where('article = :article');
        };

        $startQueryBuilder = $createQueryBuilder();
        $startQueryBuilder
            ->addSelect("pairing.start AS date")
            ->addSelect("'start' AS type")
            ->andWhere('pairing.start IS NOT NULL');

        $endQueryBuilder = $createQueryBuilder();
        $endQueryBuilder
            ->addSelect("pairing.end AS date")
            ->addSelect("'end' AS type")
            ->andWhere('pairing.end IS NOT NULL');

        $sqlAliases = [
            '/AS \w+_0/' => 'AS pairingId',
            '/AS \w+_1/' => 'AS name',
            '/AS \w+_2/' => 'AS active',
            '/AS \w+_3/' => 'AS entity',
            '/AS \w+_4/' => 'AS entityType',
            '/AS \w+_5/' => 'AS entityId',
            '/AS \w+_6/' => 'AS date',
            '/AS \w+_7/' => 'AS type',
            '/\?/' => $article->getId()
        ];

        $startSQL = $startQueryBuilder->getQuery()->getSQL();
        $startSQL = StringHelper::multiplePregReplace($sqlAliases, $startSQL);

        $endSQL = $endQueryBuilder->getQuery()->getSQL();
        $endSQL = StringHelper::multiplePregReplace($sqlAliases, $endSQL);

        $entityManager = $this->getEntityManager();
        $preparationRepository = $entityManager->getRepository(Preparation::class);
        $preparationArticleSQL = $preparationRepository->createArticleSensorPairingDataQueryUnion($article);

        $collectOrderRepository = $entityManager->getRepository(OrdreCollecte::class);
        $collectArticleSQL = $collectOrderRepository->createArticleSensorPairingDataQueryUnion($article);

        $locationRepository = $entityManager->getRepository(Emplacement::class);
        $locationSQL = $locationRepository->createArticleSensorPairingDataQueryUnion($article);

        return "
            ($startSQL)
            UNION
            ($endSQL)
            UNION
            $preparationArticleSQL
            UNION
            $collectArticleSQL
            UNION
            $locationSQL
        ";
    }

    public function getSensorPairingData(Article $article, int $start, int $count): array {
        $unionSQL = $this->createSensorPairingDataQueryUnion($article);

        $entityManager = $this->getEntityManager();
        $connection = $entityManager->getConnection();
        /** @noinspection SqlResolve */
        return $connection
            ->executeQuery("
                SELECT *
                FROM ($unionSQL) AS pairing
                ORDER BY `date` DESC
                LIMIT $count OFFSET $start
            ")
            ->fetchAllAssociative();
    }

    public function countSensorPairingData(Article $article): int {
        $unionSQL = $this->createSensorPairingDataQueryUnion($article);

        $entityManager = $this->getEntityManager();
        $connection = $entityManager->getConnection();
        $unionQuery = $connection->executeQuery("
            SELECT COUNT(*) AS count
            FROM ($unionSQL) AS pairing
        ");
        $res = $unionQuery->fetchAllAssociative();
        return $res[0]['count'] ?? 0;
    }

    public function findArticlesOnLocation(Emplacement $location): array {
        return $this->createQueryBuilder('article')
            ->join('article.statut', 'status')
            ->where('status.code IN (:availableStatuses)')
            ->andWhere('article.emplacement = :location')
            ->setParameter('availableStatuses', [Article::STATUT_ACTIF, Article::STATUT_EN_LITIGE])
            ->setParameter('location', $location)
            ->getQuery()
            ->getResult();
    }

    public function isUsedInQuantityChangingProcesses(Article $article): bool {
        $queryBuilder = $this->createQueryBuilder('article');
        $exprBuilder = $queryBuilder->expr();
        $articleIsProcessed = $queryBuilder
            ->leftJoin('article.deliveryRequestLines', 'deliveryRequestLine')
            ->leftJoin('deliveryRequestLine.request', 'deliveryRequest')
            ->leftJoin('deliveryRequest.statut', 'deliveryRequestStatus')

            ->leftJoin('article.transferRequests', 'transferRequest')
            ->leftJoin('transferRequest.status', 'transferRequestStatus')

            ->leftJoin('article.ordreCollecte', 'collectOrder')
            ->leftJoin('collectOrder.statut', 'collectOrderStatus')

            ->andWhere('article = :article')
            ->andWhere($exprBuilder->orX(
                '(deliveryRequestLine IS NOT NULL AND deliveryRequestStatus.code NOT IN (:deliveryRequestStatus_processed))',
                '(transferRequest.id IS NOT NULL AND transferRequestStatus.code NOT IN (:transferRequestStatus_processed))',
                '(collectOrder.id IS NOT NULL AND collectOrderStatus.code NOT IN (:collectOrderStatusStatus_processed))'
            ))

            ->setParameter('article', $article)
            ->setParameter('deliveryRequestStatus_processed', [Demande::STATUT_BROUILLON, Demande::STATUT_LIVRE, Demande::STATUT_LIVRE_INCOMPLETE])
            ->setParameter('transferRequestStatus_processed', [TransferRequest::DRAFT, TransferRequest::TREATED])
            ->setParameter('collectOrderStatusStatus_processed', [OrdreCollecte::STATUT_TRAITE])

            ->getQuery()
            ->getResult();

        return !empty($articleIsProcessed);
    }

    private function getCollectableArticlesQueryBuilder(?ReferenceArticle $referenceArticle, bool $lastMonthOutput = true): QueryBuilder {
        $queryBuilder = $this->createQueryBuilder("article")
            ->join('article.statut', 'statut')
            ->andWhere("article.inactiveSince IS NOT NULL")
            ->andWhere("statut.code = :inactive")
            ->setParameter("inactive", Article::STATUT_INACTIF);

        if ($lastMonthOutput) {
            $queryBuilder
                ->andWhere("article.inactiveSince > :one_month_ago")
                ->setParameter("one_month_ago", new DateTime("-1 month"));
        }

        if($referenceArticle) {
            $queryBuilder->join("article.articleFournisseur", "article_fournisseur")
                ->join("article_fournisseur.referenceArticle", "reference_article")
                ->andWhere("reference_article = :reference_article")
                ->setParameter("reference_article", $referenceArticle);
        }

        return $queryBuilder;
    }

    public function getCollectableArticlesForSelect(?string $search, ?ReferenceArticle $referenceArticle = null): array {
        $qb = $this->getCollectableArticlesQueryBuilder($referenceArticle)
            ->select("article.id AS id, article.barCode AS text");

        if (!empty($search)) {
            $qb = $qb
                ->andWhere("article.barCode LIKE :search")
                ->setParameter("search", "%$search%");
        }

        return $qb->getQuery()->getResult();
    }

    public function getCollectableMobileArticles(ReferenceArticle $referenceArticle, ?string $barcode): array {
        $queryBuilder = $this->getCollectableArticlesQueryBuilder($referenceArticle, empty($barcode))
            ->select('reference_article.reference AS reference')
            ->addSelect('reference_article.libelle AS reference_label')
            ->addSelect('article.label AS label')
            ->addSelect('article.barCode AS barcode')
            ->addSelect('join_location.label AS location')
            ->addSelect('0 AS is_ref')
            ->leftJoin('article.emplacement', 'join_location')
            ->andWhere("article.inactiveSince > :one_month_ago")
            ->setParameter("one_month_ago", new DateTime("-1 month"));

        if ($barcode) {
            $queryBuilder
                ->andWhere('article.barCode = :barcode')
                ->setParameter('barcode', $barcode);
        }

        return $queryBuilder->getQuery()->getResult();
    }

    /**
     * @return ReferenceArticle[]
     */
    public function iterateArticlesToInventory(InventoryFrequency $frequency,
                                               InventoryMission $inventoryMission): iterable
    {
        $queryBuilder = $this->createQueryBuilder('article');
        $exprBuilder = $queryBuilder->expr();
        $queryBuilder
            ->select('article')
            ->distinct()
            ->join('article.articleFournisseur', 'supplierArticle')
            ->join('supplierArticle.referenceArticle', 'referenceArticle')
            ->join('referenceArticle.category', 'category')
            ->join('referenceArticle.statut', 'referenceArticle_status')
            ->join('article.statut', 'article_status')
            ->join('category.frequency', 'frequency')
            ->leftJoin('article.inventoryMissions', 'inventoryMission')
            ->andWhere($exprBuilder->orX(
                'inventoryMission.id IS NULL',
                $exprBuilder->andX(
                    ':startDate < inventoryMission.startPrevDate',
                    ':endDate < inventoryMission.startPrevDate'
                ),
                $exprBuilder->andX(
                    ':startDate > inventoryMission.endPrevDate',
                    ':endDate > inventoryMission.endPrevDate'
                )
            ))
            ->andWhere('frequency = :frequency')
            ->andWhere('referenceArticle_status.code = :referenceArticle_status')
            ->andWhere('article_status.code IN (:article_status)')
            ->andWhere('article.dateLastInventory IS NOT NULL')
            ->andWhere('TIMESTAMPDIFF(MONTH, article.dateLastInventory, NOW()) >= frequency.nbMonths')
            ->setParameters([
                'frequency' => $frequency,
                'referenceArticle_status' => ReferenceArticle::STATUT_ACTIF,
                'article_status' => [Article::STATUT_ACTIF, Article::STATUT_EN_LITIGE],
                'startDate' => $inventoryMission->getStartPrevDate(),
                'endDate' => $inventoryMission->getEndPrevDate()
            ]);

        return $queryBuilder
            ->getQuery()
            ->toIterable();
    }

}
