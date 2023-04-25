<?php

namespace App\Repository\DeliveryRequest;

use App\Entity\Article;
use App\Entity\AverageRequestTime;
use App\Entity\DeliveryRequest\Demande;
use App\Entity\Reception;
use App\Entity\Statut;
use App\Entity\Utilisateur;
use App\Helper\QueryBuilderHelper;
use App\Service\VisibleColumnService;
use DateTime;
use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query\Expr\Join;
use Symfony\Component\HttpFoundation\InputBag;
use WiiCommon\Helper\Stream;
use WiiCommon\Helper\StringHelper;

/**
 * @method Demande|null find($id, $lockMode = null, $lockVersion = null)
 * @method Demande|null findOneBy(array $criteria, array $orderBy = null)
 * @method Demande[]    findAll()
 * @method Demande[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class DemandeRepository extends EntityRepository
{
    public function findRequestToTreatByUser(?Utilisateur $requester, int $limit) {
        $statuses = [
            Demande::STATUT_BROUILLON,
            Demande::STATUT_A_TRAITER,
            Demande::STATUT_INCOMPLETE,
            Demande::STATUT_PREPARE,
            Demande::STATUT_LIVRE_INCOMPLETE,
        ];

        $queryBuilder = $this->createQueryBuilder('demande');
        if($requester) {
            $queryBuilder->andWhere("demande.utilisateur = :requester")
                ->setParameter("requester", $requester);
        }

        $queryBuilderExpr = $queryBuilder->expr();
        return $queryBuilder
            ->select('demande')
            ->innerJoin('demande.statut', 'status')
            ->leftJoin(AverageRequestTime::class, 'art', Join::WITH, 'art.type = demande.type')
            ->andWhere($queryBuilderExpr->in('status.nom', ':statusNames'))
            ->andWhere('demande.manual = false')
            ->setParameter('statusNames', $statuses)
            ->addOrderBy(sprintf("FIELD(status.nom, '%s', '%s', '%s', '%s', '%s')", ...$statuses), 'DESC')
            ->addOrderBy("DATE_ADD(demande.createdAt, art.average, 'second')", 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->execute();
    }

    public function getProcessingTime(): array {
        $status = Demande::STATUT_LIVRE;
        $threeMonthsAgo = new DateTime("-3 months");
        $threeMonthsAgo = $threeMonthsAgo->format('Y-m-d H:i:s');

        $query = $this->getEntityManager()->getConnection()->executeQuery("
            SELECT times.id                                                   AS type,
                   SUM(UNIX_TIMESTAMP(times.max) - UNIX_TIMESTAMP(times.min)) AS total,
                   COUNT(times.id)                                            AS count
            FROM (SELECT type.id                 AS id,
                         MAX(livraison.date_fin) AS max,
                         MIN(preparation.date)   AS min
                  FROM demande
                           INNER JOIN type ON demande.type_id = type.id
                           INNER JOIN statut ON demande.statut_id = statut.id
                           INNER JOIN preparation ON demande.id = preparation.demande_id
                           INNER JOIN livraison ON preparation.id = livraison.preparation_id
                  WHERE statut.nom LIKE '$status'
                    AND demande.manual = 0
                  GROUP BY demande.id
                  HAVING MIN(preparation.date) >= '$threeMonthsAgo'
                 ) AS times
            GROUP BY times.id");

        return $query->fetchAll();
    }

    public function countByEmplacement($emplacementId)
    {
        return $this->createQueryBuilder('request')
            ->select('COUNT(request)')
            ->join('request.destination', 'destination')
            ->andWhere('destination.id = :emplacementId')
            ->setMaxResults(1)
            ->setParameter('emplacementId', $emplacementId)
            ->getQuery()
            ->getSingleScalarResult();
    }

	/**
	 * @return Demande[]|null
	 */
    public function findByDates(DateTime $dateMin, DateTime $dateMax)
    {
		$dateMax = $dateMax->format('Y-m-d H:i:s');
		$dateMin = $dateMin->format('Y-m-d H:i:s');
        return $this->createQueryBuilder('request')
            ->andWhere('request.createdAt BETWEEN :dateMin AND :dateMax')
            ->andWhere('request.manual = false')
            ->setParameters([
                'dateMin' => $dateMin,
                'dateMax' => $dateMax
            ])
            ->getQuery()
            ->execute();
    }

	public function countByUser($user): int
	{
        return $this->createQueryBuilder('request')
            ->select('COUNT(request)')
            ->andWhere('request.utilisateur = :user')
            ->setMaxResults(1)
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();
	}

	public function findByParamsAndFilters(InputBag $params, $filters, $receptionFilter, Utilisateur $user, VisibleColumnService $visibleColumnService): array
    {
        $qb = $this->createQueryBuilder("delivery_request")
            ->andWhere('delivery_request.manual = false');

        $countTotal = QueryBuilderHelper::count($qb, 'delivery_request');

        if ($receptionFilter) {
            $qb
                ->join('delivery_request.reception', 'join_reception')
                ->andWhere('join_reception.id = :reception')
                ->setParameter('reception', $receptionFilter);
        } else {
            // filtres sup
            foreach($filters as $filter) {
                switch($filter['field']) {
                    case 'statut':
                        $value = explode(',', $filter['value']);
                        $qb
                            ->join('delivery_request.statut', 'filter_status')
                            ->andWhere('filter_status.id in (:statut)')
                            ->setParameter('statut', $value);
                        break;
                    case 'type':
                        $qb
                            ->join('delivery_request.type', 'filter_type')
                            ->andWhere('filter_type.label = :type')
                            ->setParameter('type', $filter['value']);
                        break;
                    case 'utilisateurs':
                        $value = explode(',', $filter['value']);
                        $qb
                            ->join('delivery_request.utilisateur', 'filter_user')
                            ->andWhere("filter_user.id in (:id)")
                            ->setParameter('id', $value);
                        break;
                    case 'dateMin':
                        $qb->andWhere('delivery_request.createdAt >= :dateMin')
                            ->setParameter('dateMin', $filter['value'] . " 00:00:00");
                        break;
                    case 'dateMax':
                        $qb->andWhere('delivery_request.createdAt <= :dateMax')
                            ->setParameter('dateMax', $filter['value'] . " 23:59:59");
                        break;
                    case 'project':
                        $qb
                            ->leftJoin('delivery_request.project', 'filter_project')
                            ->andWhere('filter_project.id LIKE :id')
                            ->setParameter('id', $filter['value']);
                        break;
                }
            }
        }

		//Filter search
        if (!empty($params)) {
            if (!empty($params->all('search'))) {
                $search = $params->all('search')['value'];
                if (!empty($search)) {
                    $conditions = [
                        "createdAt" => "DATE_FORMAT(delivery_request.createdAt, '%d/%m/%Y') LIKE :search_value",
                        "validatedAt" => "DATE_FORMAT(delivery_request.validatedAt, '%d/%m/%Y') LIKE :search_value",
                        "requester" => "search_user.username LIKE :search_value",
                        "destination" => "search_location_destination.label LIKE :search_value",
                        "comment" => "delivery_request.commentaire LIKE :search_value",
                        "number" => "delivery_request.numero LIKE :search_value",
                        "status" => "search_status.nom LIKE :search_value",
                        "type" => "search_type.label LIKE :search_value",
                        "project" => "search_project.code LIKE :search_value",
                        "expectedAt" => "DATE_FORMAT(delivery_request.expectedAt, '%d/%m/%Y') LIKE :search_value",
                    ];

                    $visibleColumnService->bindSearchableColumns($conditions, 'deliveryRequest', $qb, $user, $search);

                    $qb
                        ->leftJoin('delivery_request.statut', 'search_status')
                        ->leftJoin('delivery_request.type', 'search_type')
                        ->leftJoin('delivery_request.project', 'search_project')
                        ->leftJoin('delivery_request.utilisateur', 'search_user')
                        ->leftJoin('delivery_request.destination', 'search_location_destination');
                }
            }

            if (!empty($params->all('order')))
            {
                $order = $params->all('order')[0]['dir'];
                if (!empty($order))
                {
                    $column = $params->all('columns')[$params->all('order')[0]['column']]['data'];
                    if ($column === 'type') {
                        $qb
                            ->leftJoin('delivery_request.type', 'order_type')
                            ->orderBy('order_type.label', $order);
                    } else if ($column === 'status') {
                        $qb
                            ->leftJoin('delivery_request.statut', 'order_status')
                            ->orderBy('order_status.nom', $order);
                    } else if ($column === 'requester') {
                        $qb
                            ->leftJoin('delivery_request.utilisateur', 'order_user')
                            ->orderBy('order_user.username', $order);
                    } else if ($column === 'destination') {
                        $qb
                            ->leftJoin('delivery_request.destination', 'order_location_destination')
                            ->orderBy('order_location_destination.label', $order);
                    } else {
                        if (property_exists(Demande::class, $column)) {
                            $qb->orderBy("delivery_request.$column", $order);
                        }
                    }
                }
            }
        }

		// compte éléments filtrés
		$countFiltered = QueryBuilderHelper::count($qb, 'delivery_request');

        if ($params->getInt('start')) $qb->setFirstResult($params->getInt('start'));
        if ($params->getInt('length')) $qb->setMaxResults($params->getInt('length'));

        return [
        	'data' => $qb->getQuery()->getResult(),
            'count' => $countFiltered,
            'total' => $countTotal
        ];
    }

    public function getIdAndLibelleBySearch($search)
    {
        return $this->createQueryBuilder('demande')
            ->select('demande.id')
            ->addSelect('demande.numero AS text')
            ->andWhere('demande.numero LIKE :search')
            ->setParameter('search', '%' . $search . '%')
            ->getQuery()
            ->execute();
    }

    public function getOlderDateToTreat(array $types = [],
                                        array $statuses = []): ?DateTime {
        if (!empty($statuses)) {
            $statusesStr = implode(',', $statuses);
            $typesStr = implode(',', $types);
            $query = $this->getEntityManager()
                ->getConnection()
                ->executeQuery("
                    SELECT preparation_date.date
                    FROM demande
                    INNER JOIN (
                        SELECT sub_demande.id AS demande_id,
                               MAX(preparation.date) AS date
                        FROM demande AS sub_demande
                        INNER JOIN preparation ON preparation.demande_id = sub_demande.id
                        WHERE sub_demande.type_id IN (${typesStr})
                          AND sub_demande.statut_id IN (${statusesStr})
                        GROUP BY sub_demande.id
                    ) AS preparation_date ON preparation_date.demande_id = demande.id
                    ORDER BY preparation_date.date ASC
                    LIMIT 1
                ");

            $res = $query->fetchOne();
            return $res
                ? (DateTime::createFromFormat('Y-m-d H:i:s', $res) ?: null)
                : null;
        }
        else {
            return null;
        }
    }

    private function createSensorPairingDataQueryUnion(Demande $deliveryRequest): string {
        $createQueryBuilder = function () {
            return $this->createQueryBuilder('deliveryRequest')
                ->select('pairing.id AS pairingId')
                ->addSelect('sensorWrapper.name AS name')
                ->addSelect('(CASE WHEN sensorWrapper.deleted = false AND pairing.active = true AND (pairing.end IS NULL OR pairing.end > NOW()) THEN 1 ELSE 0 END) AS active')
                ->addSelect('preparation.numero AS preparationNumber')
                ->addSelect('deliveryOrder.numero AS deliveryNumber')
                ->join('deliveryRequest.preparations', 'preparation')
                ->leftJoin('preparation.livraison', 'deliveryOrder')
                ->join('preparation.pairings', 'pairing')
                ->join('pairing.sensorWrapper', 'sensorWrapper')
                ->where('deliveryRequest = :deliveryRequest');
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
            '/AS \w+_3/' => 'AS preparationNumber',
            '/AS \w+_4/' => 'AS deliveryNumber',
            '/AS \w+_5/' => 'AS date',
            '/AS \w+_6/' => 'AS type',
            '/\?/' => $deliveryRequest->getId()
        ];

        $startSQL = $startQueryBuilder->getQuery()->getSQL();
        $startSQL = StringHelper::multiplePregReplace($sqlAliases, $startSQL);

        $endSQL = $endQueryBuilder->getQuery()->getSQL();
        $endSQL = StringHelper::multiplePregReplace($sqlAliases, $endSQL);

        $startDeliverySQL = $this->createQueryBuilder('deliveryRequest')
            ->select("'' AS pairingId")
            ->addSelect("'' AS name")
            ->addSelect("'' AS active")
            ->addSelect("'' AS preparationNumber")
            ->addSelect('deliveryOrder.numero AS deliveryNumber')
            ->addSelect("'' AS date")
            ->addSelect("'startOrder' AS type")
            ->join('deliveryRequest.preparations', 'preparation')
            ->join('preparation.livraison', 'deliveryOrder')
            ->where('deliveryRequest = :deliveryRequest')
            ->setParameter('deliveryRequest', $deliveryRequest)
            ->getQuery()
            ->getSQL();

        $startDeliverySQL = StringHelper::multiplePregReplace($sqlAliases, $startDeliverySQL);

        return "
            ($startSQL)
            UNION
            ($endSQL)
            UNION
            ($startDeliverySQL)
        ";
    }

    public function getSensorPairingData(Demande $deliveryRequest, int $start, int $count): array {
        $unionSQL = $this->createSensorPairingDataQueryUnion($deliveryRequest);

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

    public function countSensorPairingData(Demande $deliveryRequest): int {
        $unionSQL = $this->createSensorPairingDataQueryUnion($deliveryRequest);

        $entityManager = $this->getEntityManager();
        $connection = $entityManager->getConnection();
        $unionQuery = $connection->executeQuery("
            SELECT COUNT(*) AS count
            FROM ($unionSQL) AS pairing
        ");
        $res = $unionQuery->fetchAllAssociative();
        return $res[0]['count'] ?? 0;
    }

    public function findOneByArticle(Article $article, ?Reception $reception): ?Demande {
        $queryBuilder = $this->createQueryBuilder('request');
        $queryBuilder
            ->join('request.articleLines', 'articleLines')
            ->join('articleLines.article', 'article')
            ->andWhere('article = :article')
            ->andWhere('request.manual = false')
            ->setParameter('article', $article);
        if ($reception) {
            $queryBuilder
                ->join('request.reception', 'reception')
                ->andWhere('reception = :reception')
                ->setParameter('reception', $reception);
        }

        return $queryBuilder
            ->orderBy('request.createdAt', Criteria::DESC)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function getRequestersForReceptionExport() {
        $qb = $this->createQueryBuilder("request")
            ->select("requester.username AS username")
            ->addSelect("reception.id AS reception_id")
            ->addSelect("article.id AS article_id")
            ->leftJoin("request.articleLines", "dral")
            ->leftJoin("request.utilisateur", "requester")
            ->leftJoin("request.reception", "reception")
            ->leftJoin("dral.article", "article")
            ->getQuery()
            ->getResult();

        return Stream::from($qb)
            ->keymap(fn(array $data) => [$data["reception_id"] ."-". $data["article_id"], $data["username"]])
            ->toArray();
    }

    public function getDeliveryRequestForSelect(Utilisateur $currentUser) {
        return $this->createQueryBuilder("demande")
            ->leftJoin("demande.statut", "delivery_statut")
            ->leftJoin("demande.utilisateur", "delivery_utilisateur")
            ->where('delivery_utilisateur.username LIKE :currentUser')
            ->andWhere('delivery_statut.state = :status_draft')
            ->setParameter('currentUser', $currentUser->getUsername())
            ->setParameter('status_draft', STATUT::DRAFT)
            ->getQuery()
            ->getResult();
    }

    public function getLastNumberByDate(string $date): ?string {
        $result = $this->createQueryBuilder('delivery_request')
            ->select('delivery_request.numero')
            ->where('delivery_request.numero LIKE :value')
            ->orderBy('delivery_request.createdAt', 'DESC')
            ->addOrderBy('delivery_request.numero', 'DESC')
            ->setParameter('value', Demande::NUMBER_PREFIX . '-' . $date . '%')
            ->getQuery()
            ->execute();
        return $result ? $result[0]['numero'] : null;
    }
}
