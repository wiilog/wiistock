<?php

namespace App\Repository\PreparationOrder;

use App\Entity\Article;
use App\Entity\FiltreSup;
use App\Entity\IOT\Sensor;
use App\Entity\LocationGroup;
use App\Entity\PreparationOrder\Preparation;
use App\Entity\Statut;
use App\Entity\Utilisateur;
use App\Helper\QueryCounter;
use DateTime;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Exception;
use Generator;
use WiiCommon\Helper\StringHelper;

/**
 * @method Preparation|null find($id, $lockMode = null, $lockVersion = null)
 * @method Preparation|null findOneBy(array $criteria, array $orderBy = null)
 * @method Preparation[]    findAll()
 * @method Preparation[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PreparationRepository extends EntityRepository
{
	const DtToDbLabels = [
		'Numéro' => 'numero',
		'Statut' => 'status',
		'Date' => 'date',
		'Opérateur' => 'user',
		'Type' => 'type'
	];

    /**
     * @param Utilisateur $user
     * @param array $preparationIdsFilter
     * @return array
     */
    public function getMobilePreparations(Utilisateur $user, array $preparationIdsFilter = [])
    {
        $queryBuilder = $this->createQueryBuilder('p');
        $queryBuilder
            ->select('p.id')
            ->addSelect('p.numero as number')
            ->addSelect('dest.label as destination')
            ->addSelect('(CASE WHEN triggeringSensorWrapper.id IS NOT NULL THEN triggeringSensorWrapper.name ELSE user.username END) as requester')
            ->addSelect('t.label as type')
            ->addSelect('d.commentaire as comment')
            ->join('p.statut', 's')
            ->join('p.demande', 'd')
            ->join('d.destination', 'dest')
            ->join('d.type', 't')
            ->leftJoin('d.utilisateur', 'user')
            ->leftJoin('d.triggeringSensorWrapper', 'triggeringSensorWrapper')
            ->andWhere('(s.nom = :toTreatStatusLabel OR (s.nom = :inProgressStatusLabel AND p.utilisateur = :user))')
            ->andWhere('t.id IN (:type)')
            ->setParameters([
                'toTreatStatusLabel' => Preparation::STATUT_A_TRAITER,
                'inProgressStatusLabel' => Preparation::STATUT_EN_COURS_DE_PREPARATION,
                'user' => $user,
                'type' => $user->getDeliveryTypeIds()
            ]);

        if (!empty($preparationIdsFilter)) {
            $queryBuilder
                ->andWhere('p.id IN (:preparationIdsFilter)')
                ->setParameter('preparationIdsFilter', $preparationIdsFilter, Connection::PARAM_STR_ARRAY);
        }

        return $queryBuilder
            ->getQuery()
            ->execute();
    }

	/**
	 * @param array|null $params
	 * @param array|null $filters
	 * @return array
	 * @throws Exception
	 */
	public function findByParamsAndFilters($params, $filters)
	{
		$qb = $this->createQueryBuilder("p");

		$countTotal = QueryCounter::count($qb, 'p');

		// filtres sup
		foreach ($filters as $filter) {
			switch($filter['field']) {
				case FiltreSup::FIELD_TYPE:
					$qb
						->leftJoin('p.demande', 'd')
						->leftJoin('d.type', 't')
						->andWhere('t.label = :type')
						->setParameter('type', $filter['value']);
					break;
				case FiltreSup::FIELD_STATUT:
					$value = explode(',', $filter['value']);
					$qb
						->join('p.statut', 's')
						->andWhere('s.id in (:statut)')
						->setParameter('statut', $value);
					break;
				case FiltreSup::FIELD_USERS:
					$value = explode(',', $filter['value']);
					$qb
						->join('p.utilisateur', 'u')
						->andWhere("u.id in (:userId)")
						->setParameter('userId', $value);
					break;
				case FiltreSup::FIELD_DATE_MIN:
					$qb
						->andWhere('p.date >= :dateMin')
						->setParameter('dateMin', $filter['value']. " 00:00:00");
					break;
				case FiltreSup::FIELD_DATE_MAX:
					$qb
						->andWhere('p.date <= :dateMax')
						->setParameter('dateMax', $filter['value'] . " 23:59:59");
					break;
                case FiltreSup::FIELD_DEMANDE:
                    $qb
                        ->join('p.demande', 'demande')
                        ->andWhere('demande.id = :id')
                        ->setParameter('id', $filter['value']);
                    break;
			}
		}

		//Filter search
		if (!empty($params)) {
			if (!empty($params->get('search'))) {
				$search = $params->get('search')['value'];
				if (!empty($search)) {
					$qb
						->leftJoin('p.demande', 'd2')
						->leftJoin('d2.type', 't2')
						->leftJoin('p.utilisateur', 'p2')
						->leftJoin('p.statut', 's2')
						->andWhere('
						p.numero LIKE :value OR
						t2.label LIKE :value OR
						p2.username LIKE :value OR
						s2.nom LIKE :value
						')
						->setParameter('value', '%' . $search . '%');
				}
			}

			if (!empty($params->get('order')))
			{
				$order = $params->get('order')[0]['dir'];
				if (!empty($order))
				{
					$column = self::DtToDbLabels[$params->get('columns')[$params->get('order')[0]['column']]['data']];

					if ($column === 'status') {
						$qb
							->leftJoin('p.statut', 's3')
							->orderBy('s3.nom', $order);
					} else if ($column === 'type') {
						$qb
							->leftJoin('p.demande', 'd3')
							->leftJoin('d3.type', 't3')
							->orderBy('t3.label', $order);
					} else if ($column === 'user') {
						$qb
							->leftJoin('p.utilisateur', 'u3')
							->orderBy('u3.username', $order);
					} else {
						$qb
							->orderBy('p.' . $column, $order);
					}
				}
			}
		}

		// compte éléments filtrés
		$countFiltered = QueryCounter::count($qb, 'p');

		if ($params) {
			if (!empty($params->get('start'))) {
			    $qb->setFirstResult($params->get('start'));
            }
			if (!empty($params->get('length'))) {
			    $qb->setMaxResults($params->get('length'));
            }
		}

		return [
			'data' => $qb->getQuery()->getResult(),
			'count' => $countFiltered,
			'total' => $countTotal
		];
	}

    /**
     * @param DateTime $dateMin
     * @param DateTime $dateMax
     * @return Generator
     */
	public function iterateByDates($dateMin, $dateMax): Generator {
		$dateMax = $dateMax->format('Y-m-d H:i:s');
		$dateMin = $dateMin->format('Y-m-d H:i:s');

        $iterator = $this->createQueryBuilder('preparation')
            ->where('preparation.date BETWEEN :dateMin AND :dateMax')
            ->setParameters([
                'dateMin' => $dateMin,
                'dateMax' => $dateMax
            ])
            ->getQuery()
            ->iterate();

        foreach($iterator as $item) {
            // $item [index => preparation]
            yield array_pop($item);
        }
	}

    public function getFirstDatePreparationGroupByDemande (array $demandes)
    {
        $queryBuilder = $this->createQueryBuilder('preparation')
            ->select('demande.id AS demandeId')
            ->addSelect('MIN(preparation.date) AS firstDate')
            ->join('preparation.demande', 'demande')
            ->where('preparation.demande in (:demandes)')
            ->groupBy('demande.id')
            ->setParameter('demandes', $demandes);

        $lastDatePreparationDemande = $queryBuilder->getQuery()->execute();;
        return array_reduce($lastDatePreparationDemande, function(array $carry, $current) {
            $demandeId = $current['demandeId'];
            $firstDate = $current['firstDate'];

            $carry[$demandeId] = $firstDate;
            return $carry;
        }, []);
    }

    public function getNumeroPrepaGroupByDemande (array $demandes)
    {
        $queryBuilder = $this->createQueryBuilder('preparation')
            ->select('demande.id AS demandeId')
            ->addSelect('preparation.numero AS numeroPreparation')
            ->join('preparation.demande', 'demande')
            ->where('preparation.demande in (:demandes)')
            ->setParameter('demandes', $demandes);

        $result = $queryBuilder->getQuery()->execute();
        return array_reduce($result, function (array $carry, $current) {

            $demandeId = $current['demandeId'];
            $numeroPreparation = $current['numeroPreparation'];
            if (!isset($carry[$demandeId])) {
                $carry[$demandeId] = [];
            }
            $carry[$demandeId][] = $numeroPreparation;
            return $carry;
        }, []);
    }
	public function countByNumero(string $numero) {
	    $queryBuilder = $this
            ->createQueryBuilder('preparation')
            ->select('COUNT(preparation.id) AS counter')
            ->where('preparation.numero = :numero')
            ->setParameter('numero', $numero . '%');

	    $result = $queryBuilder
            ->getQuery()
            ->getResult();

	    return !empty($result) ? ($result[0]['counter'] ?? 0) : 0;
    }

    /**
     * @param array|null $types
     * @param array|null $statuses
     * @return int|mixed|string
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    public function countByTypesAndStatuses(?array $types, ?array $statuses): ?int {
        if (!empty($types) && !empty($statuses)) {
            $qb = $this->createQueryBuilder('preparationOrder')
                ->select('COUNT(preparationOrder)')
                ->leftJoin('preparationOrder.statut', 'status')
                ->leftJoin('preparationOrder.demande', 'request')
                ->leftJoin('request.type', 'type')
                ->where('status IN (:statuses)')
                ->andWhere('type IN (:types)')
                ->setParameter('statuses', $statuses)
                ->setParameter('types', $types);

            return $qb
                ->getQuery()
                ->getSingleScalarResult();
        }
        else {
            return [];
        }
    }

    /**
     * @param array $types
     * @param array $statuses
     * @return DateTime|null
     * @throws NonUniqueResultException
     */
    public function getOlderDateToTreat(array $types = [],
                                        array $statuses = []): ?DateTime {
        if (!empty($statuses)) {
            $res = $this
                ->createQueryBuilder('preparation')
                ->select('preparation.date AS date')
                ->innerJoin('preparation.statut', 'status')
                ->innerJoin('preparation.demande', 'request')
                ->innerJoin('request.type', 'type')
                ->andWhere('status IN (:statuses)')
                ->andWhere('type IN (:types)')
                ->andWhere('status.state IN (:treatedStates)')
                ->addOrderBy('preparation.date', 'ASC')
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

    /**
     * @param LocationGroup $locationGroup
     * @return string
     */
    public function createArticleSensorPairingDataQueryUnion(Article $article): string {
        $entityManager = $this->getEntityManager();
        $createQueryBuilder = function () use ($entityManager) {
            return $entityManager->createQueryBuilder()
                ->from(Article::class, 'article')
                ->select('pairing.id AS pairingId')
                ->addSelect('sensorWrapper.name AS name')
                ->addSelect('(CASE WHEN sensorWrapper.deleted = false AND pairing.active = true AND (pairing.end IS NULL OR pairing.end > NOW()) THEN 1 ELSE 0 END) AS active')
                ->addSelect('preparation.numero AS entity')
                ->addSelect("'" . Sensor::PREPARATION . "' AS entityType")
                ->addSelect('preparation.id AS entityId')
                ->join('article.sensorMessages', 'sensorMessage')
                ->join('sensorMessage.pairings', 'pairing')
                ->join('pairing.preparationOrder', 'preparation')
                ->join('pairing.sensorWrapper', 'sensorWrapper')
                ->where('article = :article')
                ->andWhere('pairing.article IS NULL');
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
            '/\?/' => $article->getId(),
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

}
