<?php

namespace App\Repository;

use App\Entity\Article;
use App\Entity\IOT\Sensor;
use App\Entity\LocationGroup;
use App\Entity\OrdreCollecte;
use App\Entity\Statut;
use App\Entity\Utilisateur;
use DateTime;
use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\QueryBuilder;
use Generator;
use Symfony\Component\HttpFoundation\InputBag;
use WiiCommon\Helper\StringHelper;

/**
 * @method OrdreCollecte|null find($id, $lockMode = null, $lockVersion = null)
 * @method OrdreCollecte|null findOneBy(array $criteria, array $orderBy = null)
 * @method OrdreCollecte[]    findAll()
 * @method OrdreCollecte[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class OrdreCollecteRepository extends EntityRepository
{
	const DtToDbLabels = [
		'Numéro' => 'numero',
		'Statut' => 'statut',
		'Date' => 'date',
		'Opérateur' => 'utilisateur',
		'Type' => 'type'
	];

    /**
     * @param Utilisateur $user
     * @return int
     * @throws NonUniqueResultException
     * @throws NoResultException
     */
	public function countByUser($user)
	{
		$em = $this->getEntityManager();
		$query = $em->createQuery(
		/** @lang DQL */
			"SELECT COUNT(o)
            FROM App\Entity\OrdreCollecte o
            WHERE o.utilisateur = :user"
		)->setParameter('user', $user);

		return $query->getSingleScalarResult();
	}

	/**
	 * @param Utilisateur $user
	 * @return mixed
	 */
	public function getMobileCollecte(Utilisateur $user)
	{
        $queryBuilder = $this->createCollectOrderQueryBuilder()
            ->andWhere('orderStatus.nom = :statutLabel')
            ->andWhere('collectOrder.utilisateur IS NULL OR collectOrder.utilisateur = :user')
            ->setParameters([
                'statutLabel' => OrdreCollecte::STATUT_A_TRAITER,
                'user' => $user,
            ])
            ->orderBy('collectOrder.date', Criteria::ASC);
		return $queryBuilder->getQuery()->execute();
	}

	/**
	 * @param int $ordreCollecteId
	 * @return mixed
	 */
	public function getById($ordreCollecteId)
	{
		$queryBuilder = $this->createCollectOrderQueryBuilder()
            ->andWhere('collectOrder.id = :id')
            ->setParameter('id', $ordreCollecteId);
		$result = $queryBuilder->getQuery()->execute();
		return !empty($result) ? $result[0] : null;
	}

	private function createCollectOrderQueryBuilder(): QueryBuilder  {
	    return $this->createQueryBuilder('collectOrder')
            ->select('collectOrder.id')
            ->addSelect('collectOrder.numero as number')
            ->addSelect('collectLocation.label as location_from')
            ->addSelect('collectRequest.stockOrDestruct as forStock')
            ->addSelect('(CASE WHEN triggeringSensorWrapper.id IS NOT NULL THEN triggeringSensorWrapper.name ELSE join_requester.username END) as requester')
            ->addSelect('collectType.label as type')
            ->addSelect('collectRequest.commentaire as comment')
            ->leftJoin('collectOrder.demandeCollecte', 'collectRequest')
            ->leftJoin('collectRequest.demandeur', 'join_requester')
            ->leftJoin('collectRequest.pointCollecte', 'collectLocation')
            ->leftJoin('collectOrder.statut', 'orderStatus')
            ->leftJoin('collectRequest.triggeringSensorWrapper', 'triggeringSensorWrapper')
            ->leftJoin('collectRequest.type', 'collectType');
	}

    /**
     * @param $params
     * @param $filters
     * @return array
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    public function findByParamsAndFilters(InputBag $params, $filters)
    {
        $qb = $this->createQueryBuilder('oc');

        $countTotal = $qb
            ->select('COUNT(oc)')
            ->getQuery()
            ->getSingleScalarResult();

		// filtres sup
		foreach ($filters as $filter) {
			switch ($filter['field']) {
				case 'statut':
					$value = explode(',', $filter['value']);
					$qb
						->join('oc.statut', 's')
						->andWhere('s.id in (:statut)')
						->setParameter('statut', $value);
					break;
				case 'type':
					$qb
						->join('oc.demandeCollecte', 'dc')
						->join('dc.type', 't')
						->andWhere('t.label = :type')
						->setParameter('type', $filter['value']);
					break;
				case 'utilisateurs':
					$value = explode(',', $filter['value']);
					$qb
						->join('oc.utilisateur', 'u')
						->andWhere("u.id in (:username)")
						->setParameter('username', $value);
					break;
				case 'dateMin':
					$qb
						->andWhere('oc.date >= :dateMin')
						->setParameter('dateMin', $filter['value'] . ' 00:00:00');
					break;
				case 'dateMax':
					$qb
						->andWhere('oc.date <= :dateMax')
						->setParameter('dateMax', $filter['value'] . ' 23:59:00');
					break;
				case 'demCollecte':
					$value = explode(':', $filter['value'])[0];
					$qb
						->join('oc.demandeCollecte', 'dcb')
						->andWhere('dcb.id = :id')
						->setParameter('id', $value);
					break;
				case 'demandeCollecte':
					$qb
						->join('oc.demandeCollecte', 'dcb')
						->andWhere('dcb.id = :id')
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
                        ->leftJoin('oc.statut', 's2')
                        ->leftJoin('oc.utilisateur', 'u2')
                        ->leftJoin('oc.demandeCollecte', 'dc2')
                        ->leftJoin('dc2.type', 't2')
                        ->andWhere('(
                            oc.numero LIKE :value
                            OR s2.nom LIKE :value
                            OR u2.username LIKE :value
                            OR t2.label LIKE :value
                        )')
                        ->setParameter('value', '%' . $search . '%');
                }
            }

            if (!empty($params->get('order'))) {
                $order = $params->get('order')[0]['dir'];

                if (!empty($order)) {
					$column = self::DtToDbLabels[$params->get('columns')[$params->get('order')[0]['column']]['data']];

					switch ($column) {
						case 'type':
							$qb
								->leftJoin('oc.demandeCollecte', 'dc3')
								->leftJoin('dc3.type', 't3')
								->orderBy('t3.label', $order);
							break;
						case 'statut':
							$qb
								->leftJoin('oc.statut', 's3')
								->orderBy('s3.nom', $order);
							break;
						case 'utilisateur':
							$qb
								->leftJoin('oc.utilisateur', 'u3')
								->orderBy('u3.username', $order);
							break;
						default:
							$qb->orderBy('oc.' . $column, $order);
							break;
					}
				}
			}
		}

		// compte éléments filtrés
        $countFiltered = $qb
            ->select('COUNT(oc)')
            ->getQuery()
            ->getSingleScalarResult();

        $qb->select('oc');

        if ($params->getInt('start')) $qb->setFirstResult($params->getInt('start'));
        if ($params->getInt('length')) $qb->setMaxResults($params->getInt('length'));

		$query = $qb->getQuery();

		return [
		    'data' => $query ? $query->getResult() : null,
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
		$iterator = $this->createQueryBuilder('collect_order')
            ->where('collect_order.date BETWEEN :dateMin AND :dateMax')
            ->setParameters([
                'dateMin' => $dateMin,
                'dateMax' => $dateMax
            ])
            ->getQuery()
            ->iterate();

        foreach($iterator as $item) {
            yield array_pop($item);
        }
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
            $qb = $this->createQueryBuilder('collectOrdre')
                ->select('COUNT(collectOrdre)')
                ->leftJoin('collectOrdre.statut', 'status')
                ->leftJoin('collectOrdre.demandeCollecte', 'request')
                ->leftJoin('request.type', 'type')
                ->where('status IN (:statuses)')
                ->andWhere('status IN (:statuses)')
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
        if (!empty($types) && !empty($statuses)) {
            $res = $this
                ->createQueryBuilder('collectOrder')
                ->select('collectRequest.validationDate AS date')
                ->innerJoin('collectOrder.demandeCollecte', 'collectRequest')
                ->innerJoin('collectOrder.statut', 'status')
                ->innerJoin('collectRequest.type', 'type')
                ->andWhere('status IN (:statuses)')
                ->andWhere('type IN (:types)')
                ->andWhere('status.state IN (:treatedStates)')
                ->addOrderBy('collectRequest.validationDate', 'ASC')
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
                ->addSelect('collectOrder.numero AS entity')
                ->addSelect("'" . Sensor::COLLECT_ORDER . "' AS entityType")
                ->addSelect("collectOrder.id AS entityId")
                ->join('article.sensorMessages', 'sensorMessage')
                ->join('sensorMessage.pairings', 'pairing')
                ->join('pairing.collectOrder', 'collectOrder')
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
