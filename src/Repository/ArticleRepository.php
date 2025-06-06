<?php

namespace App\Repository;

use App\Entity\Article;
use App\Entity\ArticleFournisseur;
use App\Entity\DeliveryRequest\Demande;
use App\Entity\Emplacement;
use App\Entity\FreeField\FreeField;
use App\Entity\Inventory\InventoryFrequency;
use App\Entity\Inventory\InventoryLocationMission;
use App\Entity\Inventory\InventoryMission;
use App\Entity\IOT\Sensor;
use App\Entity\Kiosk;
use App\Entity\OrdreCollecte;
use App\Entity\PreparationOrder\Preparation;
use App\Entity\ReferenceArticle;
use App\Entity\Utilisateur;
use App\Entity\VisibilityGroup;
use App\Helper\QueryBuilderHelper;
use App\Service\FieldModesService;
use DateTime;
use Doctrine\Common\Collections\Criteria;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\QueryBuilder;
use RuntimeException;
use Symfony\Component\HttpFoundation\InputBag;
use WiiCommon\Helper\Stream;
use WiiCommon\Helper\StringHelper;

/**
 * @method Article|null find($id, $lockMode = null, $lockVersion = null)
 * @method Article|null findOneBy(array $criteria, array $orderBy = null)
 * @method Article[]    findAll()
 * @method Article[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ArticleRepository extends EntityRepository {

    public const INVENTORY_MODE_FINISH = "allAvailableAndMatchingTags"; // available articles on locations + unavailable articles matching tags
    public const INVENTORY_MODE_SUMMARY = "onlyAvailable"; // available articles on locations + unavailable articles matching tags

    private const FIELD_ENTITY_NAME = [
        "location" => "emplacement",
        "unitPrice" => "prixUnitaire",
        "quantity" => "quantite",
        "RFIDtag" => "RFIDtag",
        "deliveryNote" => "deliveryNote",
        "purchaseOrder" => "purchaseOrder"
    ];

    private const FIELDS_TYPE_DATE = [
        "dateLastInventory",
        "firstUnavailableDate",
        "lastAvailableDate",
        "expiryDate",
        "stockEntryDate",
        "manufacturedAt",
        "productionDate"
    ];

    public function findExpiredToGenerate($delay = 0) {
        $since = new DateTime("now");
        $since->modify("+{$delay}day");

        return $this->createQueryBuilder("article")
            ->join('article.statut','status')
            ->where("article.expiryDate <= :since")
            ->andWhere("status.code != :consumed")
            ->setParameter("since", $since)
            ->setParameter('consumed', Article::STATUT_INACTIF)
            ->getQuery()
            ->getResult();
    }

    public function getQuantityForSupplier(ArticleFournisseur $supplier) {
        return $this
            ->createQueryBuilder('article')
            ->select('SUM(IF(statut.code = :available, article.quantite, 0))')
            ->join('article.statut', 'statut')
            ->andWhere('article.articleFournisseur = :supplier')
            ->setParameters([
                'supplier' => $supplier,
                'available' => Article::STATUT_ACTIF,
            ])
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function getReferencesByRefAndDate($refPrefix, $date)
	{
		$entityManager = $this->getEntityManager();
		$query = $entityManager->createQuery(
			'SELECT article.reference
            FROM App\Entity\Article article
            WHERE article.reference LIKE :refPrefix'
		)->setParameter('refPrefix', $refPrefix . $date . '%');

		return array_column($query->execute(), 'reference');
	}

    public function setNullByReception($id)
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            '
            UPDATE App\Entity\Article article
            SET article.receptionReferenceArticle = null
            WHERE article.receptionReferenceArticle = :id'
        )->setParameter('id', $id);
        return $query->execute();
    }

    public function findByCollecteId($id)
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "SELECT article
             FROM App\Entity\Article article
             JOIN article.collectes c
             WHERE c.id = :id
            "
        )->setParameter('id', $id);
        return $query->getResult();
    }

    public function iterateAll(Utilisateur $user, $options = []): iterable {
        $dateMin = $options["dateMin"] ?? null;
        $dateMax = $options["dateMax"] ?? null;
        $referenceTypes = $options["referenceTypes"] ?? [];
        $statuses = $options["statuses"] ?? [];
        $suppliers = $options["suppliers"] ?? [];

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

        $qb = $queryBuilder->distinct()
            ->select('referenceArticle.reference')
            ->addSelect('article.label')
            ->addSelect('article.quantite')
            ->addSelect('type.label as typeLabel')
            ->addSelect('statut.nom as statusName')
            ->addSelect('article.commentaire')
            ->addSelect('emplacement.label as empLabel')
            ->addSelect('trackingLocation.label as trackingLocationLabel')
            ->addSelect('article.barCode')
            ->addSelect('article.dateLastInventory')
            ->addSelect('article.lastAvailableDate')
            ->addSelect('article.firstUnavailableDate')
            ->addSelect('article.freeFields')
            ->addSelect('article.batch')
            ->addSelect('article.stockEntryDate')
            ->addSelect('article.expiryDate')
            ->addSelect("join_visibilityGroup.label AS visibilityGroup")
            ->addSelect("articleProject.code AS projectCode")
            ->addSelect('article.prixUnitaire')
            ->addSelect('article.purchaseOrder')
            ->addSelect('article.deliveryNote')
            ->addSelect('article.manufacturedAt')
            ->addSelect('nativeCountry.label AS nativeCountryLabel')
            ->addSelect('article.productionDate')
            ->addSelect('article.RFIDtag')
            ->addSelect('fournisseur.nom AS nomFournisseur')
            ->addSelect('articleFournisseur.reference AS RefArtFournisseur')
            ->leftJoin('article.articleFournisseur', 'articleFournisseur')
            ->leftJoin('articleFournisseur.fournisseur', 'fournisseur')
            ->leftJoin('article.emplacement', 'emplacement')
            ->leftJoin('article.type', 'type')
            ->leftJoin('article.statut', 'statut')
            ->leftJoin('articleFournisseur.referenceArticle', 'referenceArticle')
            ->leftJoin('referenceArticle.type', 'refType')
            ->leftJoin('referenceArticle.visibilityGroup', 'join_visibilityGroup')
            ->leftJoin('article.currentLogisticUnit', 'currentLogisticUnit')
            ->leftJoin('article.project', 'articleProject')
            ->leftJoin('currentLogisticUnit.lastAction', 'lastAction')
            ->leftJoin('lastAction.emplacement', 'trackingLocation')
            ->leftJoin('article.nativeCountry', 'nativeCountry');

        if (isset($dateMin) && isset($dateMax)) {
            $qb
                ->andWhere('article.stockEntryDate BETWEEN :dateMin AND :dateMax')
                ->setParameters([
                    'dateMin' => $dateMin,
                    'dateMax' => $dateMax
                ]);
        }
        if (!empty($referenceTypes)) {
            $qb
                ->andWhere('refType.id IN (:referenceTypes)')
                ->setParameter('referenceTypes', $referenceTypes);
        }
        if (!empty($statuses)) {
            $qb
                ->andWhere('statut.id IN (:statuses)')
                ->setParameter('statuses', $statuses);
        }
        if (!empty($suppliers)) {
            $qb
                ->andWhere('fournisseur.id IN (:suppliers)')
                ->setParameter('suppliers', $suppliers);
        }

        return $qb->groupBy('article.id')
            ->getQuery()
            ->toIterable();
    }

	public function getIdAndRefBySearch($search,
                                        $activeOnly = false,
                                        $field = 'reference',
                                        $referenceArticleReference = null,
                                        $activeReferenceOnly = false,
                                        Utilisateur $user = null)
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
            ->addSelect("article.{$field} AS text")
            ->addSelect('location.label AS locationLabel')
            ->addSelect('article.quantite AS quantity')
            ->leftJoin('article.emplacement', 'location')
            ->andWhere("article.{$field} LIKE :search")
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

    /**
     * @param ReferenceArticle $referenceArticle
     * @param Emplacement|null $targetLocationPicking
     * @param string|null $fieldToOrder
     * @param string|null $order
     * @return Article[] array
     */
	public function findActiveArticles(ReferenceArticle $referenceArticle,
                                       ?Emplacement     $targetLocationPicking = null,
                                       ?string          $fieldToOrder = null,
                                       ?string          $order = null,
                                       ?Demande         $ignoredDeliveryRequest = null): array
	{
	    $queryBuilder = $this->createQueryBuilder('article')
            ->distinct()
            ->join('article.articleFournisseur', 'articleFournisseur')
            ->join('articleFournisseur.referenceArticle', 'referenceArticle')
            ->join('article.statut', 'articleStatus')
            ->where('articleStatus.code = :activeStatus')
            ->andWhere('article.quantite IS NOT NULL')
            ->andWhere('article.quantite > 0')
            ->andWhere('referenceArticle = :refArticle')
            ->setParameter('refArticle', $referenceArticle)
            ->setParameter('activeStatus', Article::STATUT_ACTIF);

	    if ($targetLocationPicking) {
	        $queryBuilder
                ->addOrderBy('IF(article.emplacement = :targetLocationPicking, 1, 0)', Criteria::DESC)
                ->setParameter('targetLocationPicking', $targetLocationPicking);
        }

        if($ignoredDeliveryRequest){
            $queryHasResult = $this->createQueryBuilder("article_has_request")
                ->select('COUNT(article_has_request)')
                ->join("article_has_request.deliveryRequestLines", "lines")
                ->join("lines.request", "deliveryRequest")
                ->andWhere("deliveryRequest = :ignoredDeliveryRequest")
                ->andWhere("article_has_request.id = article.id")
                ->setMaxResults(1)
                ->getQuery()
                ->getDQL();

            $queryBuilder
                ->andWhere("($queryHasResult) = 0")
                ->setParameter('ignoredDeliveryRequest', $ignoredDeliveryRequest);
        }

	    if ($order && $fieldToOrder) {
	        $queryBuilder
                ->addOrderBy("article.$fieldToOrder", $order);
        }

	    return $queryBuilder
            ->getQuery()
            ->getResult();
	}

    public function findByParamsAndFilters(InputBag $params, $filters, Utilisateur $user): array
    {
        $entityManager = $this->getEntityManager();
        $freeFieldRepository = $entityManager->getRepository(FreeField::class);
        $queryBuilder = $this->createQueryBuilder("article");
        $articlePageFieldModes = $user->getFieldModes('article');

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

        $countQuery = $countTotal = QueryBuilderHelper::count($queryBuilder, 'article');

		// filtres sup
		foreach ($filters as $filter) {
			switch ($filter['field']) {
				case 'statut':
					$value = explode(',', $filter['value']);
					$queryBuilder
						->join('article.statut', 's_filter')
						->andWhere('s_filter.nom IN (:statut)')
						->setParameter('statut', $value);
					break;
			}
		}

		// prise en compte des paramètres issus du datatable
        if (!empty($params)) {
            if (!empty($params->all('search'))) {
                $searchValue = $params->all('search')['value'];

                if (!empty($searchValue)) {
                    $search = "%$searchValue%";

                    $ids = [];
                    $query = [];

                    // valeur par défaut si aucune valeur enregistrée pour cet utilisateur
					$searchForArticle = $user->getRechercheForArticle();
					if (empty($searchForArticle)) {
						$searchForArticle = Utilisateur::SEARCH_DEFAULT;
					}

                    foreach ($searchForArticle as $searchField) {

                        $date = DateTime::createFromFormat('d/m/Y', $searchValue);
                        $date = $date ? $date->format('Y-m-d') : null;
                        switch ($searchField) {
                            case "type":
                                $subqb = $this->createQueryBuilder("article")
                                    ->select('article.id')
                                    ->leftJoin('article.type', 't_search')
                                    ->andWhere('t_search.label LIKE :search')
                                    ->setParameter('search', $search);

                                foreach ($subqb->getQuery()->execute() as $idArray) {
                                    $ids[] = $idArray['id'];
                                }
                                break;

                            case "status":
                                $subqb = $this->createQueryBuilder("article")
                                    ->select('article.id')
                                    ->leftJoin('article.statut', 's_search')
                                    ->andWhere('s_search.nom LIKE :search')
                                    ->setParameter('search', $search);

                                foreach ($subqb->getQuery()->execute() as $idArray) {
                                    $ids[] = $idArray['id'];
                                }
                                break;
                            case "location":
                                $subqb = $this->createQueryBuilder("article")
                                    ->select('article.id')
                                    ->leftJoin('article.emplacement', 'e_search')
                                    ->andWhere('e_search.label LIKE :search')
                                    ->setParameter('search', $search);

                                foreach ($subqb->getQuery()->execute() as $idArray) {
                                    $ids[] = $idArray['id'];
                                }
                                break;
                            case "trackingLocation":
                                $subqb = $this->createQueryBuilder("article")
                                    ->select('article.id')
                                    ->leftJoin('article.trackingPack', 'trackingPack')
                                    ->leftJoin('trackingPack.lastAction', 'lastAction')
                                    ->leftJoin('lastAction.emplacement', 'e_search')
                                    ->andWhere('e_search.label LIKE :search')
                                    ->setParameter('search', $search);

                                foreach ($subqb->getQuery()->execute() as $idArray) {
                                    $ids[] = $idArray['id'];
                                }
                                break;
                            case "articleReference":
                            case "reference":
                                $subqb = $this->createQueryBuilder("article")
                                    ->select('article.id')
                                    ->leftJoin('article.articleFournisseur', 'afa')
                                    ->leftJoin('afa.referenceArticle', 'ra')
                                    ->andWhere('ra.reference LIKE :search')
                                    ->setParameter('search', $search);

                                foreach ($subqb->getQuery()->execute() as $idArray) {
                                    $ids[] = $idArray['id'];
                                }
                                break;
                            case "supplierReference":
                                $subqb = $this->createQueryBuilder("article")
                                    ->select('article.id')
                                    ->leftJoin('article.articleFournisseur', 'afa')
                                    ->andWhere('afa.reference LIKE :search')
                                    ->setParameter('search', $search);

                                foreach ($subqb->getQuery()->execute() as $idArray) {
                                    $ids[] = $idArray['id'];
                                }
                                break;
                            case "project":
                                $subqb = $this->createQueryBuilder("article")
                                    ->select('article.id')
                                    ->leftJoin('article.currentLogisticUnit', 'project_current_logistic_unit_search')
                                    ->leftJoin('project_current_logistic_unit_search.project', 'project_search')
                                    ->andWhere('project_search.code LIKE :search')
                                    ->setParameter('search', $search);

                                foreach ($subqb->getQuery()->execute() as $idArray) {
                                    $ids[] = $idArray['id'];
                                }
                                break;
                            case "nativeCountry":
                                if(in_array(FieldModesService::FIELD_MODE_VISIBLE, $articlePageFieldModes['nativeCountry'] ?? [])){
                                    $subqb = $this->createQueryBuilder("article")
                                        ->select('article.id')
                                        ->leftJoin('article.nativeCountry', 'country_search')
                                        ->andWhere('country_search.label LIKE :search')
                                        ->setParameter('search', $search);

                                    foreach ($subqb->getQuery()->execute() as $idArray) {
                                        $ids[] = $idArray['id'];
                                    }
                                }
                                break;
                            case "deliveryNoteLine":
                                if(in_array(FieldModesService::FIELD_MODE_VISIBLE, $articlePageFieldModes['deliveryNoteLine'] ?? [])){
                                    $subqb = $this->createQueryBuilder("article")
                                        ->select('article.id')
                                        ->andWhere('article.deliveryNote LIKE :search')
                                        ->setParameter('search', $search);

                                    foreach ($subqb->getQuery()->execute() as $idArray) {
                                        $ids[] = $idArray['id'];
                                    }
                                }
                                break;
                            case "purchaseOrderLine":
                                if(in_array(FieldModesService::FIELD_MODE_VISIBLE, $articlePageFieldModes['purchaseOrderLine'] ?? [])){
                                    $subqb = $this->createQueryBuilder("article")
                                        ->select('article.id')
                                        ->andWhere('article.purchaseOrder LIKE :search')
                                        ->setParameter('search', $search);

                                    foreach ($subqb->getQuery()->execute() as $idArray) {
                                        $ids[] = $idArray['id'];
                                    }
                                }
                                break;
                            default:
                                $field = self::FIELD_ENTITY_NAME[$searchField] ?? $searchField;
                                $freeFieldId = FieldModesService::extractFreeFieldId($field);
                                if(in_array(FieldModesService::FIELD_MODE_VISIBLE, $articlePageFieldModes[$field] ?? [])){
                                    if (is_numeric($freeFieldId) && $freeField = $freeFieldRepository->find($freeFieldId)) {
                                        if ($freeField->getTypage() === FreeField::TYPE_BOOL) {

                                            $lowerSearchValue = strtolower($searchValue);
                                            if (($lowerSearchValue === "oui") || ($lowerSearchValue === "non")) {
                                                $booleanValue = $lowerSearchValue === "oui" ? 1 : 0;
                                                $query[] = "JSON_SEARCH(article.freeFields, 'one', :search, NULL, '$.\"{$freeFieldId}\"') IS NOT NULL";
                                                $queryBuilder->setParameter("search", $booleanValue);
                                            }
                                        } else {
                                            $query[] = "JSON_SEARCH(LOWER(article.freeFields), 'one', :search, NULL, '$.\"$freeFieldId\"') IS NOT NULL";
                                            $queryBuilder->setParameter("search", $date ?: strtolower($search));
                                        }
                                    } else if (property_exists(Article::class, $field)) {
                                        if ($date && in_array($field, self::FIELDS_TYPE_DATE)) {
                                            $query[] = "article.$field BETWEEN :dateMin AND :dateMax";
                                            $queryBuilder
                                                ->setParameter('dateMin', $date . ' 00:00:00')
                                                ->setParameter('dateMax', $date . ' 23:59:59');
                                        } else {
                                            $query[] = "article.$field LIKE :search";
                                            $queryBuilder->setParameter('search', $search);
                                        }
                                    }
                                }
                                break;
                        }
                    }

                    foreach ($ids as $id) {
                        $query[] = 'article.id  = ' . $id;
                    }

                    if (!empty($query)) {
                        $queryBuilder->andWhere(implode(' OR ', $query));
                    }
                }

				$countQuery =  QueryBuilderHelper::count($queryBuilder, 'article');
			}

            if (!empty($params->all('order'))) {
                $order = $params->all('order')[0]['dir'];
                if (!empty($order)) {
                    $column = $params->all('columns')[$params->all('order')[0]['column']]['data'];
                    switch ($column) {
                        case "type":
                            $queryBuilder
                                ->leftJoin('article.type', 'order_type')
                                ->orderBy('order_type.label', $order);
                            break;
                        case "supplierReference":
                            $queryBuilder
                                ->leftJoin('article.articleFournisseur', 'order_supplierArticle')
                                ->orderBy('order_supplierArticle.reference', $order);
                            break;
                        case "location":
                            $queryBuilder
                                ->leftJoin("article.emplacement", "order_location")
                                ->orderBy("order_location.label", $order);
                            break;
                        case "trackingLocation":
                            $queryBuilder
                                ->leftJoin('article.trackingPack', 'order_trackingPack')
                                ->leftJoin('order_trackingPack.lastAction', 'order_lastAction')
                                ->leftJoin('order_lastAction.emplacement', 'order_lastAction_emplacement')
                                ->orderBy("order_lastAction_emplacement.label", $order);;
                            break;
                        case "articleReference":
                            $queryBuilder
                                ->leftJoin('article.articleFournisseur', 'order_articleReference_supplierArticle')
                                ->leftJoin('order_articleReference_supplierArticle.referenceArticle', 'order_referenceArticle')
                                ->orderBy('order_referenceArticle.reference', $order);
                            break;
                        case "status":
                            $queryBuilder->leftJoin('article.statut', 'order_status')
                                ->orderBy('order_status.nom', $order);
                            break;
                        case "pairing":
                            $queryBuilder->leftJoin('article.pairings', 'order_pairings')
                                ->orderBy('order_pairings.active', $order);
                            break;
                        case "lu":
                            $queryBuilder->orderBy('IF(article.currentLogisticUnit IS NULL, 0, 1)', $order);
                            break;
                        default:
                            $field = self::FIELD_ENTITY_NAME[$column] ?? $column;
                            $freeFieldId = FieldModesService::extractFreeFieldId($column);

                            if(is_numeric($freeFieldId)) {
                                /** @var FreeField $freeField */
                                $freeField = $this->getEntityManager()->getRepository(FreeField::class)->find($freeFieldId);
                                if($freeField->getTypage() === FreeField::TYPE_NUMBER) {
                                    $queryBuilder->orderBy("CAST(JSON_EXTRACT(article.freeFields, '$.\"$freeFieldId\"') AS SIGNED)", $order);
                                } else {
                                    $queryBuilder->orderBy("JSON_EXTRACT(article.freeFields, '$.\"$freeFieldId\"')", $order);
                                }
                            } else if (property_exists(Article::class, $field)) {
                                $queryBuilder->orderBy("article.$field", $order);
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
            "SELECT COUNT(article)
            FROM App\Entity\Article article
            JOIN article.statut s
            WHERE s.nom = :active"
		)->setParameter('active', Article::STATUT_ACTIF);

        return $query->getSingleScalarResult();
    }

    public function countAll()
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "SELECT COUNT(article)
            FROM App\Entity\Article article"
		);

        return $query->getSingleScalarResult();
    }

    public function getByPreparationsIds($preparationsIds): array {
        return $this->createQueryBuilder('article')
            ->select('article.reference AS reference')
            ->addSelect('join_location.label AS location')
            ->addSelect('article.label AS label')
            ->addSelect('join_preparationLine.quantityToPick AS quantity')
            ->addSelect('0 AS is_ref')
            ->addSelect('join_preparation.id AS id_prepa')
            ->addSelect('article.barCode AS barCode')
            ->addSelect('join_referenceArticle.reference AS reference_article_reference')
            ->addSelect('join_targetLocationPicking.label AS targetLocationPicking')
            ->addSelect('join_lineLogisticUnit.id AS lineLogisticUnitId')
            ->addSelect('join_lineLogisticUnit.code AS lineLogisticUnitCode')
            ->addSelect('join_lineLogisticUnitNature.id AS lineLogisticUnitNatureId')
            ->addSelect('join_lineLogisticUnitLastOngoingDropLocation.label AS lineLogisticUnitLocation')
            ->leftJoin('article.emplacement', 'join_location')
            ->join('article.preparationOrderLines', 'join_preparationLine')
            ->leftJoin('join_preparationLine.pack', 'join_lineLogisticUnit')
            ->leftJoin('join_lineLogisticUnit.lastOngoingDrop', 'join_lineLogisticUnitLastOngoingDrop')
            ->leftJoin('join_lineLogisticUnitLastOngoingDrop.emplacement', 'join_lineLogisticUnitLastOngoingDropLocation')
            ->leftJoin('join_lineLogisticUnit.nature', 'join_lineLogisticUnitNature')
            ->join('join_preparationLine.preparation', 'join_preparation')
            ->join('article.articleFournisseur', 'join_supplierArticle')
            ->join('join_supplierArticle.referenceArticle', 'join_referenceArticle')
            ->leftJoin('join_preparationLine.targetLocationPicking', 'join_targetLocationPicking')
            ->andWhere('join_preparation.id IN (:preparationsIds)')
            ->andWhere('article.quantite > 0')
            ->setParameter('preparationsIds', $preparationsIds, Connection::PARAM_STR_ARRAY)
            ->getQuery()
            ->getResult();
	}

    public function getArticlePrepaForPickingByUser($user, array $preparationIdsFilter = [], ?bool $displayPickingLocation = false) {
        $queryBuilder = $this->createQueryBuilder('article')
            ->select('DISTINCT article.reference AS reference')
            ->addSelect('article.label AS label')
            ->addSelect('join_article_location.label AS location')
            ->addSelect('article.quantite AS quantity')
            ->addSelect('referenceArticle.reference AS reference_article')
            ->addSelect('referenceArticle.barCode AS reference_barCode')
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
            ->addSelect('IF(:displayPickingLocation = true AND join_targetLocationPicking.id = join_article_location.id, 1, 0) AS pickingPriority')
            ->join('article.articleFournisseur', 'articleFournisseur')
            ->join('articleFournisseur.referenceArticle', 'referenceArticle')
            ->join('article.statut', 'articleStatut')
            ->leftJoin('article.emplacement', 'join_article_location')
            ->join('referenceArticle.preparationOrderReferenceLines', 'preparationOrderReferenceLines')
            ->leftJoin('preparationOrderReferenceLines.targetLocationPicking', 'join_targetLocationPicking')
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
            ->setParameter('null', null)
            ->setParameter('displayPickingLocation', $displayPickingLocation);

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
            ->select('join_location.label AS location')
            ->addSelect('join_ref_article.reference AS reference')
            ->addSelect('article.label AS label')
            ->addSelect('join_preparationOrderLines.quantityToPick AS quantity')
            ->addSelect('0 as is_ref')
            ->addSelect('join_delivery.id AS id_livraison')
            ->addSelect('article.barCode AS barcode')
            ->addSelect('join_targetLocationPicking.label AS targetLocationPicking')
            ->addSelect('join_currentLogisticUnit.id AS currentLogisticUnitId')
            ->addSelect('join_currentLogisticUnit.code AS currentLogisticUnitCode')
            ->addSelect('join_nature.id AS currentLogisticUnitNatureId')
            ->addSelect('join_currentLogisticUnitLocation.label AS currentLogisticUnitLocation')
            ->leftJoin('article.emplacement', 'join_location')
            ->join('article.preparationOrderLines', 'join_preparationOrderLines')
            ->join('join_preparationOrderLines.preparation', 'join_preparation')
            ->join('join_preparation.livraison', 'join_delivery')
            ->join('article.articleFournisseur', 'join_article_supplier')
            ->join('join_article_supplier.referenceArticle', 'join_ref_article')
            ->leftJoin('join_preparationOrderLines.targetLocationPicking', 'join_targetLocationPicking')
            ->leftJoin('article.currentLogisticUnit', 'join_currentLogisticUnit')
            ->leftJoin('join_currentLogisticUnit.lastOngoingDrop', 'join_lastOngoingDrop')
            ->leftJoin('join_lastOngoingDrop.emplacement', 'join_currentLogisticUnitLocation')
            ->leftJoin('join_currentLogisticUnit.nature', 'join_nature')
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
                ->addSelect('article_location.label AS location')
                ->addSelect('article.quantite AS quantity')
                ->addSelect('transferOrder.id AS transfer_order_id')
                ->join('article.transferRequests', 'transferRequest')
                ->join('transferRequest.order', 'transferOrder')
                ->join('article.articleFournisseur', 'articleFournisseur')
                ->join('articleFournisseur.referenceArticle', 'referenceArticle')
                ->leftJoin('article.emplacement', 'article_location')
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
			 article.label,
			 article.quantite as quantity,
			 0 as is_ref, oc.id as id_collecte,
			 article.barCode,
			 ra.libelle as reference_label
			FROM App\Entity\Article article
			JOIN article.articleFournisseur artf
			JOIN artf.referenceArticle ra
			LEFT JOIN article.emplacement e
			JOIN article.ordreCollecte oc
			LEFT JOIN oc.statut s"
		);
	}

    public function findOneByReference(?string $reference): ?Article {
        return $this->createQueryBuilder('article')
            ->andWhere('article.reference = :reference')
            ->setParameter('reference', $reference)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function countByLocation(Emplacement $location): int {
        return $this->createQueryBuilder('article')
            ->select('COUNT(article.id)')
            ->andWhere('article.emplacement = :location')
            ->setParameter('location', $location)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countByMission($mission)
    {
        return $this->createQueryBuilder('article')
            ->select('COUNT(article)')
            ->join('article.inventoryMissions', 'mission')
            ->andWhere('mission = :mission')
            ->setParameter('mission', $mission)
            ->getQuery()
            ->getSingleScalarResult();
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
                'typeQuantity' => ReferenceArticle::QUANTITY_TYPE_ARTICLE,
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
		"SELECT article.barCode
		FROM App\Entity\Article article
		WHERE article.barCode LIKE :barCode
		ORDER BY article.barCode DESC
		")
            ->setParameter('barCode', Article::BARCODE_PREFIX . $dateCode . '%')
            ->setMaxResults(1);

        $result = $query->execute();
        return $result ? $result[0]['barCode'] : null;
    }

	public function findOneByBarCodeAndLocation(string $barCode, string $location): ?Article {
        $queryBuilder = $this
            ->createQueryBuilderByBarCodeAndLocation($barCode, $location)
            ->setMaxResults(1);

        return $queryBuilder
            ->getQuery()
            ->getOneOrNullResult();
    }

	public function getOneArticleByBarCodeAndLocation(string $barCode, ?string $location) {
        $queryBuilder = $this
            ->createQueryBuilderByBarCodeAndLocation($barCode, $location, true)
            ->addSelect('article.id as id')
            ->addSelect('article.barCode as barCode')
            ->addSelect('articleFournisseur_reference.libelle as label')
            ->addSelect('articleFournisseur_reference.reference as reference')
            ->addSelect('articleFournisseur_reference.typeQuantite as typeQuantity')
            ->addSelect('article_location.label as location')
            ->addSelect('article.quantite as quantity')
            ->addSelect('referenceArticle_status.nom as reference_status')
            ->addSelect('0 as is_ref')
            ->addSelect('current_logistic_unit.id as currentLogisticUnitId')
            ->addSelect('current_logistic_unit.code as currentLogisticUnitCode')
            ->addSelect('article_status.code as articleStatusCode')
            ->join('article.articleFournisseur', 'article_articleFournisseur')
            ->join('article_articleFournisseur.referenceArticle', 'articleFournisseur_reference')
            ->join('articleFournisseur_reference.statut', 'referenceArticle_status')
            ->join('article.emplacement', 'article_location')
            ->leftJoin('article.currentLogisticUnit', 'current_logistic_unit');

        $result = $queryBuilder->getQuery()->execute();
        return !empty($result) ? $result[0] : null;
    }

    private function createQueryBuilderByBarCodeAndLocation(string  $barCode,
                                                            ?string $location,
                                                            bool    $includeUnavailable = false): QueryBuilder {
        $queryBuilder = $this->createQueryBuilder('article');
        $queryBuilder
            ->join('article.statut', 'article_status')
            ->andWhere('article.barCode = :barCode')
            ->setParameter('barCode', $barCode);

        if (!$includeUnavailable) {
            $queryBuilder
                ->andWhere('article_status.code IN (:articleStatuses)')
                ->setParameter('articleStatuses', [Article::STATUT_ACTIF, Article::STATUT_EN_LITIGE]);
        }


        if($location) {
            $queryBuilder
                ->join('article.emplacement', 'emplacement')
                ->andWhere('emplacement.label = :location')
                ->setParameter('location', $location);
        }

        return $queryBuilder;
    }

    public function findActiveOrDisputeForReference($reference, Emplacement $emplacement) {
        return $this->createQueryBuilder("article")
            ->join("article.articleFournisseur", "af")
            ->leftJoin("article.statut", "articleStatut")
            ->where("articleStatut.nom IN (:statuses)")
            ->andWhere("af.referenceArticle = :reference")
            ->andWhere('article.emplacement = :location')
            ->setParameter("reference", $reference)
            ->setParameter("location", $emplacement)
            ->setParameter("statuses", [Article::STATUT_ACTIF, Article::STATUT_EN_LITIGE])
            ->getQuery()
            ->getResult();
    }

    public function findAvailableArticlesToInventory(array $rfidTags,
                                                     array $locations,
                                                     array $options = []): array {
        $mode = $options["mode"] ?? null;
        $groupByStorageRule = $options["groupByStorageRule"] ?? false;

        $queryBuilder = $this->createQueryBuilder("article");
        $exprBuilder = $queryBuilder->expr();

        $queryBuilder
            ->join("article.emplacement", "articleLocation")
            ->join("article.statut", "articleStatus")

            // All article to inventory match a storage rule of the referenceArticle
            ->join("article.articleFournisseur", "supplierArticle")
            ->join("supplierArticle.referenceArticle", "referenceArticle")
            ->join("referenceArticle.storageRules", "storageRule")
            ->andWhere("storageRule.location = articleLocation") // storageRule.location = location article

            ->andWhere("articleLocation IN (:locations)") // location article is in locations to treat
            ->andWhere("articleStatus.code IN (:statuses)")
            ->andWhere("article.RFIDtag IS NOT NULL")
            ->setParameter("rfidTags", $rfidTags)
            ->setParameter("locations", $locations)
            ->setParameter("statuses", [Article::STATUT_ACTIF, Article::STATUT_INACTIF]);

        switch ($mode) {
            case self::INVENTORY_MODE_FINISH:
                $queryBuilder
                    ->andWhere($exprBuilder->orX(
                        "article.RFIDtag IN (:rfidTags)",
                        "articleStatus.code = :availableStatus"
                    ))
                    ->setParameter("availableStatus", Article::STATUT_ACTIF);
                break;
            case self::INVENTORY_MODE_SUMMARY:
                $queryBuilder
                    ->andWhere("article.RFIDtag IN (:rfidTags)");
                break;
            default:
                throw new RuntimeException('Invalid mode');
        }

        if ($groupByStorageRule) {
            $result = $queryBuilder
                ->addSelect("storageRule.id AS storageRuleId")
                ->getQuery()
                ->getResult();

            return Stream::from($result)
                ->keymap(fn(array $row) => [$row["storageRuleId"], $row[0]], true)
                ->toArray();
        }
        else {
            return $queryBuilder
                ->getQuery()
                ->getResult();
        }
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

    public function getForSelect(?string $term, string $status = null, int $referenceId = null) {
        $qb = $this->createQueryBuilder("article")
            ->select("article.id AS id, article.barCode AS text");

        if($status !== null) {
            $qb->join("article.statut", "status")
                ->andWhere("status.code = :status")
                ->setParameter("status", $status);
        }

        if($referenceId !== null) {
            $qb->addSelect('emplacement.label AS location, article.quantite AS quantity')
                ->join("article.articleFournisseur", "articleFournisseur")
                ->join("articleFournisseur.referenceArticle", "referenceArticle")
                ->leftJoin("article.emplacement", "emplacement")
                ->where('referenceArticle.id = :idReference')
                ->setParameter("idReference", $referenceId);
        }

        return $qb
            ->andWhere("article.barCode LIKE :term")
            ->setParameter("term", "%$term%")
            ->setMaxResults(100)
            ->getQuery()
            ->getArrayResult();
    }

    public function isInLogisticUnit(string $barcode): ?Article {
        return $this->createQueryBuilder("article")
            ->join("article.currentLogisticUnit", "current_logistic_unit")
            ->andWhere("article.barCode = :barcode")
            ->setParameter("barcode", $barcode)
            ->getQuery()
            ->getOneOrNullResult();
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

    private function getCollectableArticlesQueryBuilder(?ReferenceArticle $referenceArticle, bool $useCollectableDelay = true): QueryBuilder {
        $queryBuilder = $this->createQueryBuilder("article")
            ->join('article.statut', 'statut')
            ->andWhere("article.inactiveSince IS NOT NULL")
            ->andWhere("statut.code = :inactive")
            ->setParameter("inactive", Article::STATUT_INACTIF);

        if ($useCollectableDelay) {
            $queryBuilder
                ->andWhere("article.inactiveSince > :collectable_delay")
                ->setParameter("collectable_delay", new DateTime(Article::COLLECTABLE_DELAY));
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
            ->leftJoin('article.emplacement', 'join_location');

        if ($barcode) {
            $queryBuilder
                ->andWhere('article.barCode = :barcode')
                ->setParameter('barcode', $barcode);
        }

        return $queryBuilder->getQuery()->getResult();
    }

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

    public function quantityForRefOnLocation(ReferenceArticle $referenceArticle, Emplacement $location) {
        return $this->createQueryBuilder('article')
            ->select('SUM(article.quantite) as total')
            ->andWhere('reference_article = :reference_article')
            ->andWhere('emplacement = :location')
            ->andWhere('statut.code = :active')
            ->join('article.articleFournisseur', 'article_fournisseur')
            ->join('article_fournisseur.referenceArticle', 'reference_article')
            ->join('article.emplacement', 'emplacement')
            ->join('article.statut', 'statut')
            ->setParameters([
                'reference_article' => $referenceArticle,
                'location' => $location,
                'active' => Article::STATUT_ACTIF
            ])
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function getLatestsKioskPrint(Kiosk $kiosk) {
        return $this->createQueryBuilder('article')
            ->andWhere('article.createdOnKioskAt IS NOT null')
            ->andWhere('article.kiosk = :kiosk')
            ->orderBy('article.createdOnKioskAt', 'DESC')
            ->setMaxResults(3)
            ->setParameter('kiosk', $kiosk)
            ->getQuery()
            ->getResult();
    }

    public function countInventoryLocationMission(Article $article): int {
        return $this->createQueryBuilder('article')
            ->select("COUNT(article.id)")
            ->join(InventoryLocationMission::class, 'inventoryLocationMission', Join::WITH, "article MEMBER OF inventoryLocationMission.articles")
            ->andWhere("article = :article")
            ->setParameter("article", $article)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findOneByReferenceAndStockManagement(ReferenceArticle $referenceArticle): ?Article {
        if ($referenceArticle->getTypeQuantite() !== ReferenceArticle::QUANTITY_TYPE_ARTICLE) {
            return null;
        }
        $stockManagement = $referenceArticle->getStockManagement() ?? ReferenceArticle::DEFAULT_STOCK_MANAGEMENT;

        $queryBuilder = $this->createQueryBuilder("article")
            ->innerJoin("article.statut", "status", Join::WITH, "status.code = :status")
            ->innerJoin("article.articleFournisseur", "supplier_article")
            ->innerJoin("supplier_article.referenceArticle", "reference_article", Join::WITH, "reference_article = :referenceArticle");

        if ($stockManagement === ReferenceArticle::STOCK_MANAGEMENT_FIFO) {
            $queryBuilder->orderBy("article.stockEntryDate", Criteria::ASC);
        } else if ($stockManagement === ReferenceArticle::STOCK_MANAGEMENT_FEFO) {
            $queryBuilder->orderBy("article.expiryDate", Criteria::ASC);
        }

        return $queryBuilder
            ->setParameter("referenceArticle", $referenceArticle)
            ->setParameter("status", Article::STATUT_ACTIF)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
