<?php

namespace App\Repository;

use App\Entity\AverageRequestTime;
use App\Entity\Collecte;
use App\Entity\Statut;
use App\Entity\Utilisateur;
use App\Helper\QueryBuilderHelper;
use DateTime;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\Query\Expr\Join;
use Symfony\Component\HttpFoundation\InputBag;
use WiiCommon\Helper\StringHelper;

/**
 * @method Collecte|null find($id, $lockMode = null, $lockVersion = null)
 * @method Collecte|null findOneBy(array $criteria, array $orderBy = null)
 * @method Collecte[]    findAll()
 * @method Collecte[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CollecteRepository extends EntityRepository
{
    private const DtToDbLabels = [
        'Création' => 'date',
        'Validation' => 'validationDate',
        'Demandeur' => 'demandeur',
        'Numéro' => 'numero',
        'Objet' => 'objet',
        'Statut' => 'statut',
        'Type' => 'type',
    ];

    public function findByStatutLabelAndUser($statutLabel, $user)
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "SELECT c
            FROM App\Entity\Collecte c
            JOIN c.statut s
            WHERE s.nom = :statutLabel AND c.demandeur = :user "
        )->setParameters([
            'statutLabel' => $statutLabel,
            'user' => $user,
        ]);
        return $query->execute();
    }

    public function countByStatut($statut)
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "SELECT COUNT(c)
            FROM App\Entity\Collecte c
            WHERE c.statut = :statut "
        )->setParameter('statut', $statut);
        return $query->getSingleScalarResult();
    }

    public function countByEmplacement($emplacementId)
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
            "SELECT COUNT(c)
            FROM App\Entity\Collecte c
            JOIN c.pointCollecte pc
            WHERE pc.id = :emplacementId"
        )->setParameter('emplacementId', $emplacementId);

        return $query->getSingleScalarResult();
    }

    /**
     * @param Utilisateur $user
     * @return int
     * @throws NonUniqueResultException
     * @throws NoResultException
     */
    public function countByUser($user) {
        return $this->createQueryBuilder("c")
            ->select("COUNT(c)")
            ->where("c.demandeur = :user")
            ->setParameter("user", $user)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findByParamsAndFilters(InputBag $params, $filters) {
        $qb = $this->createQueryBuilder("c");

        $countTotal =  QueryBuilderHelper::count($qb, 'c');

        // filtres sup
        foreach ($filters as $filter) {
            switch ($filter['field']) {
                case 'statut':
					$value = explode(',', $filter['value']);
					$qb
						->join('c.statut', 's')
						->andWhere('s.id in (:statut)')
						->setParameter('statut', $value);
					break;
                case 'type':
                    $qb
                        ->join('c.type', 't')
                        ->andWhere('t.label = :type')
                        ->setParameter('type', $filter['value']);
                    break;
                case 'utilisateurs':
                    $value = explode(',', $filter['value']);
                    $qb
                        ->join('c.demandeur', 'd')
                        ->andWhere("d.id in (:id)")
                        ->setParameter('id', $value);
                    break;
                case 'dateMin':
                    $qb->andWhere('c.date >= :dateMin')
                        ->setParameter('dateMin', $filter['value'] . " 00:00:00");
                    break;
                case 'dateMax':
                    $qb->andWhere('c.date <= :dateMax')
                        ->setParameter('dateMax', $filter['value'] . " 23:59:59");
                    break;
            }
        }

        //Filter search
        if (!empty($params)) {
			if (!empty($params->all('search'))) {
				$search = $params->all('search')['value'];
				if (!empty($search)) {
                    $exprBuilder = $qb->expr();
					$qb
						->andWhere(
                            $exprBuilder->orX(
						        'c.objet LIKE :value',
						        'c.numero LIKE :value',
						        'demandeur_search.username LIKE :value',
						        'type_search.label LIKE :value',
						        'statut_search.nom LIKE :value'
                            )
                        )
						->setParameter('value', '%' . $search . '%')
                        ->leftJoin('c.demandeur', 'demandeur_search')
                        ->leftJoin('c.type', 'type_search')
                        ->leftJoin('c.statut', 'statut_search');
				}
			}

			if (!empty($params->all('order'))) {
				$order = $params->all('order')[0]['dir'];
				if (!empty($order)) {
					$column = self::DtToDbLabels[$params->all('columns')[$params->all('order')[0]['column']]['data']];

					switch ($column) {
						case 'type':
							$qb
								->leftJoin('c.type', 't2')
								->orderBy('t2.label', $order);
							break;
						case 'statut':
							$qb
								->leftJoin('c.statut', 's2')
								->orderBy('s2.nom', $order);
							break;
						case 'demandeur':
							$qb
								->leftJoin('c.demandeur', 'd2')
								->orderBy('d2.username', $order);
							break;
						default:
							$qb->orderBy('c.' . $column, $order);
							break;
					}
				}
			}
		}

		// compte éléments filtrés
		$countFiltered =  QueryBuilderHelper::count($qb, 'c');

        if ($params->getInt('start')) $qb->setFirstResult($params->getInt('start'));
        if ($params->getInt('length')) $qb->setMaxResults($params->getInt('length'));

		return [
		    'data' => $qb->getQuery()->getResult(),
			'count' => $countFiltered,
			'total' => $countTotal
		];
	}

	public function getIdAndLibelleBySearch($search) {
	    return $this->createQueryBuilder("c")
            ->select("c.id, c.numero AS text")
            ->where("c.numero LIKE :search")
            ->setParameter("search", "%$search%")
            ->getQuery()
            ->getArrayResult();
	}

	public function findByDates($dateMin, $dateMax)
    {
        $dateMax = $dateMax->format('Y-m-d H:i:s');
        $dateMin = $dateMin->format('Y-m-d H:i:s');

        $queryBuilder = $this->createQueryBuilder('collecte')
            ->select('collecte')

            ->where('collecte.date BETWEEN :dateMin AND :dateMax')

            ->setParameters([
                'dateMin' => $dateMin,
                'dateMax' => $dateMax
            ]);

        return $queryBuilder
            ->getQuery()
            ->getResult();
    }

    public function getProcessingTime() {
        $threeMonthsAgo = new DateTime("-3 month");

        return $this->createQueryBuilder("collect")
            ->select("collect_type.id AS type")
            ->addSelect("SUM(UNIX_TIMESTAMP(collect_order.treatingDate) - UNIX_TIMESTAMP(collect.validationDate)) AS total")
            ->addSelect("COUNT(collect) AS count")
            ->join("collect.type", "collect_type")
            ->join("collect.statut", "status")
            ->join("collect.ordreCollecte", "collect_order")
            ->where("status.nom = :collect")
            ->andWhere("collect.validationDate >= :from")
            ->andWhere("collect_order.date IS NOT NULL")
            ->groupBy("collect.type")
            ->setParameter("from", $threeMonthsAgo)
            ->setParameter("collect", Collecte::STATUT_COLLECTE)
            ->getQuery()
            ->getArrayResult();
    }

    public function findRequestToTreatByUser(?Utilisateur $requester, int $limit) {
        $statuses = [
            Collecte::STATUT_BROUILLON,
            Collecte::STATUT_A_TRAITER,
            Collecte::STATUT_INCOMPLETE,
        ];

        $queryBuilder = $this->createQueryBuilder('request');
        if($requester) {
            $queryBuilder->andWhere("request.demandeur = :requester")
                ->setParameter("requester", $requester);
        }

        $queryBuilderExpr = $queryBuilder->expr();
        return $queryBuilder
            ->innerJoin('request.statut', 'status')
            ->leftJoin(AverageRequestTime::class, 'art', Join::WITH, 'art.type = request.type')
            ->andWhere($queryBuilderExpr->in('status.nom', ':statusNames'))
            ->setParameter('statusNames', $statuses)
            ->addOrderBy(sprintf("FIELD(status.nom, '%s', '%s', '%s')", ...$statuses), 'DESC')
            ->addOrderBy("DATE_ADD(request.validationDate, art.average, 'second')", 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->execute();
    }

    /**
     * @param array $types
     * @param array $statuses
     * @return DateTime|null
     * @throws NonUniqueResultException
     */
    public function getOlderDateToTreat(array $types = [],
                                        array $statuses = []): ?DateTime {
        if (!empty($types) && !empty($statuses)) {
            $res = $this
                ->createQueryBuilder('collect')
                ->select('collect.validationDate AS date')
                ->innerJoin('collect.statut', 'status')
                ->innerJoin('collect.type', 'type')
                ->andWhere('status IN (:statuses)')
                ->andWhere('type IN (:types)')
                ->andWhere('status.state IN (:treatedStates)')
                ->addOrderBy('collect.validationDate', 'ASC')
                ->setParameter('statuses', $statuses)
                ->setParameter('types', $types)
                ->setParameter('treatedStates', [Statut::PARTIAL, Statut::NOT_TREATED])
                ->setMaxResults(1)
                ->getQuery()
                ->getOneOrNullResult();
            return $res['date'] ?? null;
        }
        else {
            return null;
        }
    }

    private function createSensorPairingDataQueryUnion(Collecte $collect): string {
        $createQueryBuilder = function () {
            return $this->createQueryBuilder('collectRequest')
                ->select('pairing.id AS pairingId')
                ->addSelect('sensorWrapper.name AS name')
                ->addSelect('(CASE WHEN sensorWrapper.deleted = false AND pairing.active = true AND (pairing.end IS NULL OR pairing.end > NOW()) THEN 1 ELSE 0 END) AS active')
                ->addSelect('collectOrder.numero AS orderNumber')
                ->join('collectRequest.ordreCollecte', 'collectOrder')
                ->join('collectOrder.pairings', 'pairing')
                ->join('pairing.sensorWrapper', 'sensorWrapper')
                ->where('collectRequest = :collectRequest');
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
            '/AS \w+_3/' => 'AS orderNumber',
            '/AS \w+_4/' => 'AS date',
            '/AS \w+_5/' => 'AS type',
            '/\?/' => $collect->getId()
        ];

        $startSQL = $startQueryBuilder->getQuery()->getSQL();
        $startSQL = StringHelper::multiplePregReplace($sqlAliases, $startSQL);

        $endSQL = $endQueryBuilder->getQuery()->getSQL();
        $endSQL = StringHelper::multiplePregReplace($sqlAliases, $endSQL);

        return "
            ($startSQL)
            UNION
            ($endSQL)
        ";
    }

    public function getSensorPairingData(Collecte $collect, int $start, int $count): array {
        $unionSQL = $this->createSensorPairingDataQueryUnion($collect);

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

    public function countSensorPairingData(Collecte $collect): int {
        $unionSQL = $this->createSensorPairingDataQueryUnion($collect);

        $entityManager = $this->getEntityManager();
        $connection = $entityManager->getConnection();
        $unionQuery = $connection->executeQuery("
            SELECT COUNT(*) AS count
            FROM ($unionSQL) AS pairing
        ");
        $res = $unionQuery->fetchAllAssociative();
        return $res[0]['count'] ?? 0;
    }

    public function getCollectRequestForSelect(Utilisateur $currentUser) {
        return $this->createQueryBuilder("collecte")
            ->leftJoin("collecte.statut", "collect_statut")
            ->leftJoin("collecte.demandeur", "collect_utilisateur")
            ->where('collect_utilisateur.username LIKE :currentUser')
            ->andWhere('collect_statut.state = :status_draft')
            ->setParameter('currentUser', $currentUser->getUsername())
            ->setParameter('status_draft', STATUT::DRAFT)
            ->getQuery()
            ->getResult();
    }

}
