<?php

namespace App\Repository\DeliveryRequest;

use App\Entity\Article;
use App\Entity\AverageRequestTime;
use App\Entity\DeliveryRequest\Demande;
use App\Entity\Reception;
use App\Entity\Statut;
use App\Entity\Utilisateur;
use App\Helper\QueryCounter;
use DateTime;
use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
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
    private const DtToDbLabels = [
        'Date' => 'date',
        'Demandeur' => 'demandeur',
        'Statut' => 'statut',
        'Numéro' => 'numero',
        'Type' => 'type',
    ];

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
            ->setParameter('statusNames', $statuses)
            ->addOrderBy(sprintf("FIELD(status.nom, '%s', '%s', '%s', '%s', '%s')", ...$statuses), 'DESC')
            ->addOrderBy("DATE_ADD(demande.date, art.average, 'second')", 'ASC')
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
                  GROUP BY demande.id
                  HAVING MIN(preparation.date) >= '$threeMonthsAgo'
                 ) AS times
            GROUP BY times.id");

        return $query->fetchAll();
    }

    public function findByStatutAndUser($statut, $user)
    {
        return $this->createQueryBuilder('request')
            ->andWhere('request.statut = :statut AND request.utilisateur = :user')
            ->setParameters([
                'statut' => $statut,
                'user' => $user
            ])
            ->getQuery()
            ->execute();
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
	 * @param DateTime $dateMin
	 * @param DateTime $dateMax
	 * @return Demande[]|null
	 */
    public function findByDates($dateMin, $dateMax)
    {
		$dateMax = $dateMax->format('Y-m-d H:i:s');
		$dateMin = $dateMin->format('Y-m-d H:i:s');
        return $this->createQueryBuilder('request')
            ->andWhere('request.date BETWEEN :dateMin AND :dateMax')
            ->setParameters([
                'dateMin' => $dateMin,
                'dateMax' => $dateMax
            ])
            ->getQuery()
            ->execute();
    }

    public function getLastNumeroByPrefixeAndDate($prefixe, $date)
	{

        $queryBuilder = $this->createQueryBuilder('request')
            ->select('request.numero')
            ->andWhere('request.numero LIKE :value')
            ->orderBy('request.numero', Criteria::DESC)
            ->setParameter('value', $prefixe . $date . '%');

        $result = $queryBuilder
            ->getQuery()
            ->execute();

		return $result ? $result[0]['numero'] : null;
	}

	/**
	 * @param Utilisateur $user
	 * @return int
	 * @throws NonUniqueResultException
	 * @throws NoResultException
	 */
	public function countByUser($user)
	{
        return $this->createQueryBuilder('request')
            ->select('COUNT(request)')
            ->andWhere('request.utilisateur = :user')
            ->setMaxResults(1)
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();
	}

	public function findByParamsAndFilters(InputBag $params, $filters, $receptionFilter)
    {
        $qb = $this->createQueryBuilder("d");

        $countTotal = QueryCounter::count($qb, 'd');

        if ($receptionFilter) {
            $qb
                ->join('d.reception', 'r')
                ->andWhere('r.id = :reception')
                ->setParameter('reception', $receptionFilter);
        } else {
            // filtres sup
            foreach($filters as $filter) {
                switch($filter['field']) {
                    case 'statut':
                        $value = explode(',', $filter['value']);
                        $qb
                            ->join('d.statut', 's')
                            ->andWhere('s.id in (:statut)')
                            ->setParameter('statut', $value);
                        break;
                    case 'type':
                        $qb
                            ->join('d.type', 't')
                            ->andWhere('t.label = :type')
                            ->setParameter('type', $filter['value']);
                        break;
                    case 'utilisateurs':
                        $value = explode(',', $filter['value']);
                        $qb
                            ->join('d.utilisateur', 'u')
                            ->andWhere("u.id in (:id)")
                            ->setParameter('id', $value);
                        break;
                    case 'dateMin':
                        $qb->andWhere('d.date >= :dateMin')
                            ->setParameter('dateMin', $filter['value'] . " 00:00:00");
                        break;
                    case 'dateMax':
                        $qb->andWhere('d.date <= :dateMax')
                            ->setParameter('dateMax', $filter['value'] . " 23:59:59");
                        break;
                }
            }
        }

		//Filter search
        if (!empty($params)) {
            if (!empty($params->get('search'))) {
                $search = $params->get('search')['value'];
                if (!empty($search)) {
                    $qb
						->join('d.statut', 's2')
						->join('d.type', 't2')
						->join('d.utilisateur', 'u2')
                        ->andWhere("(
                            DATE_FORMAT(d.date, '%d/%m/%Y') LIKE :value
                            OR u2.username LIKE :value
                            OR d.numero LIKE :value
                            OR s2.nom LIKE :value
                            OR t2.label LIKE :value
                        )")
                        ->setParameter('value', '%' . $search . '%');
                }
            }

            if (!empty($params->get('order')))
            {
                $order = $params->get('order')[0]['dir'];
                if (!empty($order))
                {
                    $column = self::DtToDbLabels[$params->get('columns')[$params->get('order')[0]['column']]['data']];
                    if ($column === 'type') {
                        $qb
                            ->leftJoin('d.type', 'search_type')
                            ->orderBy('search_type.label', $order);
                    } else if ($column === 'statut') {
                        $qb
                            ->leftJoin('d.statut', 'search_status')
                            ->orderBy('search_status.nom', $order);
                    } else if ($column === 'demandeur') {
                        $qb
                            ->leftJoin('d.utilisateur', 'search_user')
                            ->orderBy('search_user.username', $order);
                    } else {
                        if (property_exists(Demande::class, $column)) {
                            $qb->orderBy("d.$column", $order);
                        }
                    }
                }
            }
        }

		// compte éléments filtrés
		$countFiltered = QueryCounter::count($qb, 'd');

        if ($params->getInt('start')) $qb->setFirstResult($params->getInt('start'));
        if ($params->getInt('length')) $qb->setMaxResults($params->getInt('length'));

        return [
        	'data' => $qb->getQuery()->getResult(),
            'count' => $countFiltered,
            'total' => $countTotal
        ];
    }

    /**
     * @param $search
     * @return mixed
     */
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

            $res = $query->fetchColumn();
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
            ->setParameter('article', $article);
        if ($reception) {
            $queryBuilder
                ->join('request.reception', 'reception')
                ->andWhere('reception = :reception')
                ->setParameter('reception', $reception);
        }

        return $queryBuilder
            ->orderBy('request.date', Criteria::DESC)
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
        $qb = $this->createQueryBuilder("demande");
        return $qb->select("demande.id AS id")
            ->addSelect("demande.numero AS number")
            ->addSelect("demande.commentaire AS comment")
            ->addSelect("delivery_type.label AS type")
            ->addSelect("delivery_destination.label AS destination")
            ->addSelect("DATE_FORMAT(demande.date,'%d-%c-%Y %H:%i') AS creationDate")
            ->leftJoin("demande.type", "delivery_type")
            ->leftJoin("demande.statut", "delivery_statut")
            ->leftJoin("demande.destination", "delivery_destination")
            ->leftJoin("demande.utilisateur", "delivery_utilisateur")
            ->where('delivery_utilisateur.username LIKE :currentUser')
            ->andWhere('delivery_statut.state = :status_draft')
            ->setParameter('currentUser', $currentUser->getUsername())
            ->setParameter('status_draft', STATUT::DRAFT)
            ->getQuery()
            ->getArrayResult();
    }
}
