<?php

namespace App\Repository;

use App\Entity\Reception;
use App\Entity\ReferenceArticle;
use App\Entity\Utilisateur;
use App\Helper\QueryBuilderHelper;
use App\Service\FieldModesService;
use DateTime;
use Doctrine\Common\Collections\Order;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query\Expr\Join;
use Symfony\Component\HttpFoundation\InputBag;
use WiiCommon\Helper\Stream;

/**
 * @method Reception|null find($id, $lockMode = null, $lockVersion = null)
 * @method Reception|null findOneBy(array $criteria, array $orderBy = null)
 * @method Reception[]    findAll()
 * @method Reception[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ReceptionRepository extends EntityRepository
{

    private const DtToDbLabels = [
        'Date' => 'date',
        'DateFin' => 'dateFinReception',
        'Commentaire' => 'commentaire',
        'Statut' => 'statut',
        'Fournisseur' => 'fournisseur',
        'emergency' => 'emergency',
        'storageLocation' => 'storageLocation',
        'urgence' => 'emergencyTriggered',
        'dateAttendue' =>'dateAttendue'
    ];

    public function getLastNumberByDate(string $date): ?string {
        $result = $this->createQueryBuilder('reception')
            ->select('reception.number AS number')
            ->where('reception.number LIKE :value')
            ->orderBy('reception.date', 'DESC')
            ->addOrderBy('reception.number', 'DESC')
            ->addOrderBy('reception.id', 'DESC')
            ->setParameter('value', Reception::NUMBER_PREFIX . $date . '%')
            ->getQuery()
            ->execute();
        return $result ? $result[0]['number'] : null;
    }

	public function countByUser(Utilisateur $user): int
	{
		$em = $this->getEntityManager();
		$query = $em->createQuery(
		/** @lang DQL */
			"SELECT COUNT(r)
            FROM App\Entity\Reception r
            WHERE r.utilisateur = :user"
		)->setParameter('user', $user);

		return $query->getSingleScalarResult();
	}

    /**
     * @param ReferenceArticle $referenceArticle
     * @param DateTime $start
     * @param DateTime $end
     * @return Reception[]
     */
    public function getAwaitingWithReference(ReferenceArticle $referenceArticle, DateTime $start, DateTime $end) {
        $start->setTime(0, 0, 0);
        $end->setTime(23, 59, 59);
        return $this->createQueryBuilder('reception')
            ->join('reception.statut', 'statut')
            ->join('reception.lines', 'line')
            ->join('line.receptionReferenceArticles', 'referenceLine')
            ->where('referenceLine.referenceArticle = :reference')
            ->andWhere('reception.dateAttendue BETWEEN :start and :end')
            ->andWhere('statut.code = :validated')
            ->setParameters([
                'start' => $start,
                'end' => $end,
                'validated' => Reception::STATUT_EN_ATTENTE,
                'reference' => $referenceArticle
            ])
            ->orderBy('reception.dateAttendue', 'ASC')
            ->getQuery()
            ->execute();
    }

    public function iterateByDates(DateTime $dateMin, DateTime $dateMax): iterable {
        return $this->createQueryBuilder("reception")
            ->distinct()
            ->select("reception.id AS id")
            ->addSelect("article.id AS articleId")
            ->addSelect("referenceArticle.id AS referenceArticleId")
            ->addSelect("reception.number AS number")
            ->addSelect("reception.orderNumber AS orderNumber")
            ->addSelect("provider.nom AS providerName")
            ->addSelect("user.username AS userUsername")
            ->addSelect("status.nom AS statusName")
            ->addSelect("reception.date AS date")
            ->addSelect("reception.dateFinReception AS dateFinReception")
            ->addSelect("reception.commentaire AS commentaire")
            ->addSelect("receptionReferenceArticle.quantiteAR AS receptionRefArticleQuantiteAR")
            ->addSelect("receptionReferenceArticle.quantite AS receptionRefArticleQuantite")
            ->addSelect("referenceArticle.reference AS referenceArticleReference")
            ->addSelect("referenceArticle.typeQuantite AS referenceArticleTypeQuantite")
            ->addSelect("referenceArticle.libelle AS referenceArticleLibelle")
            ->addSelect("pack.code AS currentLogisticUnit")
            ->addSelect("referenceArticle.quantiteStock AS referenceArticleQuantiteStock")
            ->addSelect("referenceArticleType.label AS referenceArticleTypeLabel")
            ->addSelect("referenceArticle.barCode AS referenceArticleBarcode")
            ->addSelect("articleReferenceArticle.reference AS articleReference")
            ->addSelect("article.label AS articleLabel")
            ->addSelect("article.quantite AS articleQuantity")
            ->addSelect("articleType.label AS articleTypeLabel")
            ->addSelect("articleReferenceArticle.barCode AS articleReferenceArticleBarcode")
            ->addSelect("article.barCode as articleBarcode")
            ->addSelect("reception.manualUrgent AS receptionEmergency")
            ->addSelect("IF(receptionReferenceArticle.stockEmergencies IS NOT EMPTY OR reception.manualUrgent = true, 1,0) AS referenceEmergency")
            ->addSelect("join_storageLocation.label AS storageLocation")
            ->addSelect("receptionReferenceArticle.unitPrice AS receptionReferenceArticleUnitPrice")
            ->andWhere("reception.date BETWEEN :dateMin AND :dateMax")
            ->leftJoin("reception.fournisseur", "provider")
            ->leftJoin("reception.utilisateur", "user")
            ->leftJoin("reception.statut", "status")
            ->leftJoin("reception.storageLocation", "join_storageLocation")
            ->leftJoin("reception.lines", "receptionLine")
            ->leftJoin("receptionLine.receptionReferenceArticles", "receptionReferenceArticle")
            ->leftJoin("receptionReferenceArticle.referenceArticle", "referenceArticle")
            ->leftJoin("referenceArticle.type", "referenceArticleType")
            ->leftJoin("receptionReferenceArticle.articles", "article")
            ->leftJoin("article.currentLogisticUnit", "pack")
            ->leftJoin("article.type", "articleType")
            ->leftJoin("article.articleFournisseur", "articleFournisseur")
            ->leftJoin("articleFournisseur.referenceArticle", "articleReferenceArticle")
            ->setParameters([
                "dateMin" => $dateMin,
                "dateMax" => $dateMax,
            ])
            ->getQuery()
            ->toIterable();
    }

    public function findByParamAndFilters(InputBag $params, $filters, Utilisateur $user, FieldModesService $fieldModesService)
    {
        $qb = $this->createQueryBuilder("reception");
        $exprBuilder = $qb->expr();

        $countTotal = QueryBuilderHelper::count($qb, 'reception');

        // filtres sup
        foreach ($filters as $filter) {
            switch ($filter['field']) {
                case 'statut':
                    $value = explode(',', $filter['value']);
                    $qb
                        ->join('reception.statut', 's')
                        ->andWhere('s.id in (:statut)')
                        ->setParameter('statut', $value);
                    break;
                case 'purchaseRequest':
                    $value = $filter['value'];
                    $qb
                        ->join('reception.purchaseRequestLines', 'purchaseRequestLines')
                        ->join('purchaseRequestLines.purchaseRequest', 'purchaseRequestLines_purchaseRequest')
                        ->andWhere('purchaseRequestLines_purchaseRequest.id = :purchaseRequest')
                        ->setParameter('purchaseRequest', $value);
                    break;
                case 'commandList':
                    $value = Stream::from(explode(',', $filter['value']))
                        ->map(fn($v) => explode(':', $v)[0])
                        ->toArray();

                    if (!empty($value)) {
                        $ors = $qb->expr()->orX();
                        foreach ($value as $command) {
                            $keyParameter = "search_command_{$ors->count()}";
                            $ors->add("JSON_CONTAINS(reception.orderNumber, :$keyParameter, '$') = true");
                            $qb->setParameter($keyParameter, "\"$command\"");
                        }

                        $qb->andWhere($ors);
                    }
                    break;
                case 'receivers':
                    $values = array_map(function ($value) {
                        return explode(":", $value)[0];
                    }, explode(',', $filter['value']));
                    $qb
                        ->join('reception.demandes', 'filter_request')
                        ->join('filter_request.utilisateur', 'filter_request_user');

                    $exprBuilder = $qb->expr();
                    $OROperands = [];
                    foreach ($values as $index => $receiver) {
                        $OROperands[] = "filter_request_user.id = :user$index";
                        $qb->setParameter("user$index", $receiver);
                    }
                    $qb->andWhere('(' . $exprBuilder->orX(...$OROperands) . ')');
                    break;
                case 'providers':
                    $value = explode(',', $filter['value']);
                    $qb
                        ->join('reception.fournisseur', 'f')
                        ->andWhere("f.id in (:fournisseur)")
                        ->setParameter('fournisseur', $value);
                    break;
                case 'utilisateurs':
                    $value = explode(',', $filter['value']);
                    $qb
                        ->join('reception.utilisateur', 'filter_user')
                        ->andWhere("filter_user.id in (:reception_user)")
                        ->setParameter('reception_user', $value);
                    break;
                case 'dateMin':
                    $qb->andWhere('reception.date >= :dateMin')
                        ->setParameter('dateMin', $filter['value'] . ' 00:00:00');
                    break;
                case 'dateMax':
                    $qb->andWhere('reception.date <= :dateMax')
                        ->setParameter('dateMax', $filter['value'] . ' 23:59:59');
                    break;
                case 'expectedDate':
                    $dateExpectedMin = ($filter['value'] . ' 00:00:00 ');
                    $dateExpectedMax = ($filter['value'] . ' 23:59:59 ');
                    $qb->andWhere('reception.dateAttendue BETWEEN :dateExpectedMin AND :dateExpectedMax')
                        ->setParameter('dateExpectedMin', $dateExpectedMin)
                        ->setParameter('dateExpectedMax', $dateExpectedMax);
                    break;
                case 'emergency':
                    $valueFilter = ((int)($filter['value'] ?? 0));
                    if ($valueFilter) {
                        $qb
                            ->leftJoin('reception.lines', 'join_reception_lines')
                            ->leftJoin('join_reception_lines.receptionReferenceArticles', 'join_reception_references_articles')
                            ->andWhere(
                                $exprBuilder->orX(
                                    'join_reception_references_articles.stockEmergencies IS NOT EMPTY',
                                    'reception.manualUrgent = true'
                                )
                            );
                    }
                    break;
                case 'emergencyId':
                    $valueFilter = ((int)($filter['value'] ?? 0));
                    if ($valueFilter) {
                        $qb
                            ->innerJoin("reception.lines", "receptionLine")
                            ->innerJoin("receptionLine.receptionReferenceArticles", "receptionReferenceArticles")
                            ->innerJoin("receptionReferenceArticles.stockEmergencies", "stockEmergency",Join::WITH, "stockEmergency.id = :emergencyId")
                            ->setParameter('emergencyId', $valueFilter);
                    }
                    break;
            }
        }
        //Filter search
        if (!empty($params)) {
            if (!empty($params->all('search'))) {
                $search = $params->all('search')['value'];
                if (!empty($search)) {
                    $conditions = [
                        "Date" => "DATE_FORMAT(reception.date, '%d/%m/%Y') LIKE :search_value",
                        "number" => "reception.number LIKE :search_value",
                        "dateAttendue" => "DATE_FORMAT(reception.dateAttendue, '%d/%m/%Y') LIKE :search_value",
                        "DateFin" => "DATE_FORMAT(reception.dateFinReception, '%d/%m/%Y') LIKE :search_value",
                        "orderNumber" => "reception.orderNumber LIKE :search_value",
                        "receiver" => null,
                        "Fournisseur" => "search_provider.nom LIKE :search_value",
                        "Statut" => "search_status.nom LIKE :search_value",
                        "storageLocation" => "search_storage_location.label LIKE :search_value",
                        "Commentaire" => "reception.commentaire LIKE :search_value",
                        "deliveries" => null,
                        "user" => "search_user.username LIKE :search_value",
                    ];

                    $fieldModesService->bindSearchableColumns($conditions, 'reception', $qb, $user, $search);

                    $qb
						->leftJoin('reception.statut', 'search_status')
						->leftJoin('reception.fournisseur', 'search_provider')
                        ->leftJoin('reception.demandes', 'search_request')
                        ->leftJoin('search_request.utilisateur', 'search_request_user')
                        ->leftJoin('reception.utilisateur', 'search_user')
                        ->leftJoin('reception.storageLocation', 'search_storage_location');
                }
            }

            if (!empty($params->all('order')))
            {
                foreach ($params->all('order') as $sort) {
                    $order = $sort['dir'];
                    if (!empty($order))
                    {
                        $columnName = $params->all('columns')[$sort['column']]['data'];
                        $column = self::DtToDbLabels[$columnName] ?? $columnName;
                        if ($column === 'statut') {
                            $qb
                                ->leftJoin('reception.statut', 's2')
                                ->addOrderBy('s2.nom', $order);
                        } else if ($column === 'fournisseur') {
                            $qb
                                ->leftJoin('reception.fournisseur', 'u2')
                                ->addOrderBy('u2.nom', $order);
                        } else if ($column === 'storageLocation') {
                            $qb
                                ->leftJoin('reception.storageLocation', 'join_storageLocation')
                                ->addOrderBy('join_storageLocation.label', $order);
                        } else if($column === 'deliveryFee'){
                            $qb
                                ->leftJoin('reception.purchaseRequestLines', 'purchaseRequestLines')
                                ->leftJoin('purchaseRequestLines.purchaseRequest', 'purchaseRequestLines_purchaseRequest')
                                ->addOrderBy('purchaseRequestLines_purchaseRequest.deliveryFee', $order);
                        } else if ($column === 'user') {
                            $qb
                                ->leftJoin('reception.utilisateur', 'join_utilisateur')
                                ->addOrderBy('join_utilisateur.username', $order);
                        } else if (property_exists(Reception::class, $column)) {
                            $qb
                                ->addOrderBy('reception.' . $column, $order);
                        }
                    }
                }
            }
        }

        // compte éléments filtrés
        $countFiltered = QueryBuilderHelper::count($qb, 'reception');

        if ($params->getInt('start')) $qb->setFirstResult($params->getInt('start'));
        if ($params->getInt('length')) $qb->setMaxResults($params->getInt('length'));

        $query = $qb->getQuery();

        return [
            'data' => $query ? $query->getResult() : null,
            'count' => $countFiltered,
            'total' => $countTotal
        ];
    }

    public function getReceptionDeliveryFees(DateTime $receptionDateMin,
                                             DateTime $receptionDateMax): array {
        $result = $this->createQueryBuilder("reception")
            ->select("reception.id AS reception_id")
            ->addSelect("MAX(join_purchaseRequest.deliveryFee) AS delivery_fee")
            ->innerJoin("reception.purchaseRequestLines", "join_purchaseRequestLine")
            ->innerJoin("join_purchaseRequestLine.purchaseRequest", "join_purchaseRequest", Join::WITH, "join_purchaseRequest.deliveryFee IS NOT NULL")
            ->andWhere("reception.date BETWEEN :dateMin AND :dateMax")
            ->addGroupBy("reception_id")
            ->setParameters([
                "dateMin" => $receptionDateMin,
                "dateMax" => $receptionDateMax,
            ])
            ->getQuery()
            ->getResult();

        return Stream::from($result)
            ->keymap(static fn(array $data) => [$data["reception_id"], $data["delivery_fee"]])
            ->toArray();
    }

    public function getDeliveryRequestersOnReception(DateTime $receptionDateMin,
                                                     DateTime $receptionDateMax): array {
        $result = $this->createQueryBuilder("reception")
            ->select("join_requester.username AS username")
            ->addSelect("reception.id AS reception_id")
            ->addSelect("join_article.id AS article_id")
            ->innerJoin("reception.demandes", "join_request")
            ->innerJoin("join_request.utilisateur", "join_requester")
            ->innerJoin("join_request.articleLines", "join_article_line")
            ->innerJoin("join_article_line.article", "join_article")
            ->andWhere("reception.date BETWEEN :dateMin AND :dateMax")
            ->setParameters([
                "dateMin" => $receptionDateMin,
                "dateMax" => $receptionDateMax,
            ])
            ->getQuery()
            ->getResult();

        return Stream::from($result)
            ->keymap(fn(array $data) => [$data["reception_id"] . "-" . $data["article_id"], $data["username"]])
            ->toArray();
    }

    public function getMobileReceptions(): array {
        $maxNumberOfReceptions = 100;

        $countLineWithPackQueryBuilder = $this->createQueryBuilder("count_line_with_pack_reception")
            ->select("COUNT(join_count_line_with_pack_line.id)")
            ->andWhere("count_line_with_pack_reception.id = reception.id")
            ->join("count_line_with_pack_reception.lines", "join_count_line_with_pack_line")
            ->join("join_count_line_with_pack_line.pack", "join_count_line_with_pack_pack");

        $sumReferenceQuantityQueryBuilder = $this->createQueryBuilder("sum_reference_quantity_reception")
            ->select("SUM(COALESCE(join_sum_reference_quantity_reception_reference_article.quantiteAR, 0) - COALESCE(join_sum_reference_quantity_reception_reference_article.quantite, 0))")
            ->andWhere("sum_reference_quantity_reception.id = reception.id")
            ->join("sum_reference_quantity_reception.lines", "join_sum_reference_quantity_line")
            ->join("join_sum_reference_quantity_line.receptionReferenceArticles", "join_sum_reference_quantity_reception_reference_article");

        // get reception which can be treated
        $queryBuilder = $this->createQueryBuilder("reception");
        $exprBuilder = $queryBuilder->expr();

        return $queryBuilder
            ->select("reception.id AS id")
            ->addSelect("join_supplier.nom AS supplier")
            ->addSelect("reception.orderNumber AS orderNumber")
            ->addSelect("reception.dateAttendue AS expectedDate")
            ->addSelect("reception.dateCommande AS orderDate")
            ->addSelect("join_user.username AS user")
            ->addSelect("join_carrier.label AS carrier")
            ->addSelect("join_location.label AS location")
            ->addSelect("join_storageLocation.label AS storageLocation")
            ->addSelect("IF(join_reception_references_articles.stockEmergencies IS NOT EMPTY OR reception.manualUrgent = true, 1,0) AS emergency_articles")
            ->addSelect("reception.manualUrgent AS emergency_manual")
            ->addSelect("reception.number AS number")
            ->addSelect("join_status.nom AS status")

            ->join("reception.statut", "join_status")
            ->leftJoin("reception.storageLocation", "join_storageLocation")
            ->leftJoin("reception.location", "join_location")
            ->leftJoin("reception.transporteur", "join_carrier")
            ->leftJoin("reception.fournisseur", "join_supplier")
            ->leftJoin("reception.utilisateur", "join_user")
            ->leftJoin('reception.lines', 'join_reception_lines')
            ->leftJoin('join_reception_lines.receptionReferenceArticles', 'join_reception_references_articles')

            // Select only reception without packs
            ->andWhere($exprBuilder->eq("({$countLineWithPackQueryBuilder->getDQL()})", 0))

            // Select only reception with quantity to receive
            ->andWhere($exprBuilder->gt("({$sumReferenceQuantityQueryBuilder->getDQL()})", 0))

            // Select only reception not finished
            ->andWhere("join_status.code IN (:states)")

            ->orderBy("reception.dateAttendue", Order::Descending->value)
            ->addOrderBy("reception.id", Order::Descending->value)

            ->setMaxResults($maxNumberOfReceptions)

            ->setParameter("states", [
                Reception::STATUT_RECEPTION_PARTIELLE,
                Reception::STATUT_EN_ATTENTE,
            ])
            ->getQuery()
            ->getResult();
    }

    public function countStockEmergenciesByReception(Reception $reception): int {
        $queryBuilder = $this->createQueryBuilder('reception');
        $exprBuilder = $queryBuilder->expr();

        $queryBuilder
            ->select($exprBuilder->count('join_stock_emergency'))
            ->leftJoin('reception.lines', 'join_reception_lines')
            ->leftJoin('join_reception_lines.receptionReferenceArticles', 'join_reception_references_articles')
            ->leftJoin('join_reception_references_articles.stockEmergencies', 'join_stock_emergency')
            ->andWhere($exprBuilder->eq("reception",":reception"))
            ->setParameter("reception", $reception);

        return $queryBuilder
            ->getQuery()
            ->getSingleScalarResult();
    }
}
