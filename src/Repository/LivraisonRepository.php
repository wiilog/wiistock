<?php

namespace App\Repository;

use App\Entity\Emplacement;
use App\Entity\FiltreSup;
use App\Entity\Livraison;
use App\Entity\ReferenceArticle;
use App\Entity\Statut;
use App\Entity\Utilisateur;
use App\Helper\QueryBuilderHelper;
use Doctrine\Common\Collections\Criteria;
use DateTime;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Exception;
use Symfony\Component\HttpFoundation\InputBag;

/**
 * @method Livraison|null find($id, $lockMode = null, $lockVersion = null)
 * @method Livraison|null findOneBy(array $criteria, array $orderBy = null)
 * @method Livraison[]    findAll()
 * @method Livraison[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class LivraisonRepository extends EntityRepository
{
	const DtToDbLabels = [
		'Numéro' => 'numero',
		'Statut' => 'statut',
		'Date' => 'date',
		'Opérateur' => 'utilisateur',
		'Type' => 'type'
	];

    public function countByLocation(Emplacement $location): int {
        return $this->createQueryBuilder('delivery_order')
            ->select('COUNT(delivery_order.id)')
            ->andWhere('delivery_order.destination = :location')
            ->setParameter('location', $location)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @param Utilisateur $user
     * @return array[]
     */
	public function getMobileDelivery(Utilisateur $user, ?int $maxResult = 100)
	{
	    $queryBuilder = $this->createQueryBuilder('delivery_order')
            ->select('delivery_order.id AS id')
            ->addSelect('delivery_order.numero AS number')
            ->addSelect('destination.label as location')
            ->addSelect('join_type.label as type')
            ->addSelect('(CASE WHEN triggeringSensorWrapper.id IS NOT NULL THEN triggeringSensorWrapper.name ELSE user.username END) as requester')
            ->addSelect('join_preparationLocation.label AS preparationLocation')
            ->addSelect('request.commentaire AS comment')
            ->addSelect('request.expectedAt AS expectedAt')
            ->addSelect('projet.code AS project')
            ->join('delivery_order.statut', 'status')
            ->join('delivery_order.preparation', 'preparation')
            ->join('preparation.demande', 'request')
            ->join('request.destination', 'destination')
            ->join('request.type', 'join_type')
            ->leftJoin('request.project', 'projet')
            ->leftJoin('request.utilisateur', 'user')
            ->leftJoin('request.triggeringSensorWrapper', 'triggeringSensorWrapper')
            ->join('preparation.endLocation', 'join_preparationLocation')
            ->where('status.nom = :statusLabel')
            ->andWhere('(delivery_order.utilisateur IS NULL OR delivery_order.utilisateur = :user)')
            ->andWhere('join_type.id IN (:typeIds)')
            ->orderBy('delivery_order.date', Criteria::ASC)
            ->setMaxResults($maxResult)
            ->setParameters([
                'statusLabel' => Livraison::STATUT_A_TRAITER,
                'user' => $user,
                'typeIds' => $user->getDeliveryTypeIds()
            ]);

		return $queryBuilder
            ->getQuery()
            ->execute();
	}

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
			"SELECT COUNT(l)
            FROM App\Entity\Livraison l
            WHERE l.utilisateur = :user"
		)->setParameter('user', $user);

		return $query->getSingleScalarResult();
	}

	/**
	 * @param array|null $params
	 * @param array|null $filters
	 * @return array
	 * @throws Exception
	 */
	public function findByParamsAndFilters(InputBag $params, $filters)
	{
		$qb = $this->createQueryBuilder("livraison")
            ->join('livraison.preparation', 'preparation')
            ->join('preparation.demande', 'demande');

		$countTotal = QueryBuilderHelper::count($qb, 'livraison');

		// filtres sup
		foreach ($filters as $filter) {
			switch ($filter['field']) {
				case FiltreSup::FIELD_STATUT:
					$value = explode(',', $filter['value']);
					$qb
						->join('livraison.statut', 's')
						->andWhere('s.id in (:statut)')
						->setParameter('statut', $value);
					break;
                case FiltreSup::FIELD_TYPE:
					$qb
						->leftJoin('demande.type', 'type')
						->andWhere('type.label = :type')
						->setParameter('type', $filter['value']);
					break;
				case FiltreSup::FIELD_USERS:
					$value = explode(',', $filter['value']);
					$qb
						->join('livraison.utilisateur', 'user')
						->andWhere("user.id in (:userId)")
						->setParameter('userId', $value);
					break;
				case FiltreSup::FIELD_DEMANDE:
                    $qb
                        ->andWhere('demande.id = :id')
                        ->setParameter('id', $filter['value']);
                    break;
				case FiltreSup::FIELD_DATE_MIN:
					$qb
						->andWhere('livraison.date >= :dateMin')
						->setParameter('dateMin', $filter['value'] . " 00:00:00");
					break;
				case FiltreSup::FIELD_DATE_MAX:
					$qb
						->andWhere('livraison.date <= :dateMax')
						->setParameter('dateMax', $filter['value'] . " 23:59:59");
					break;
			}
		}

		//Filter search
		if (!empty($params)) {
			if (!empty($params->all('search'))) {
				$search = $params->all('search')['value'];
				if (!empty($search)) {
					$qb
						->leftJoin('livraison.statut', 's2')
						->leftJoin('livraison.utilisateur', 'u2')
						->leftJoin('demande.type', 't2')
						->andWhere('(
                            livraison.numero LIKE :value
                            OR s2.nom LIKE :value
                            OR u2.username LIKE :value
                            OR t2.label LIKE :value
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

					if ($column === 'statut') {
						$qb
							->leftJoin('livraison.statut', 's3')
							->orderBy('s3.nom', $order);
					} else if ($column === 'utilisateur') {
						$qb
							->leftJoin('livraison.utilisateur', 'u3')
							->orderBy('u3.username', $order);
					} else if ($column === 'type') {
						$qb
							->leftJoin('demande.type', 't3')
							->orderBy('t3.label', $order);
					} else {
						$qb
							->orderBy('livraison.' . $column, $order);
					}
				}
			}
		}

		// compte éléments filtrés
		$countFiltered = QueryBuilderHelper::count($qb, 'livraison');

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
	 * @return Livraison[]|null
	 */
	public function iterateByDates($dateMin, $dateMax)
	{
		$iterator = $this->createQueryBuilder('livraison')
            ->where('livraison.date BETWEEN :dateMin AND :dateMax')
            ->setParameters([
			'dateMin' => $dateMin,
			'dateMax' => $dateMax
		])
            ->getQuery()
            ->iterate();

        foreach($iterator as $item) {
            // $item [index => article array]
            yield array_pop($item);
        };

	}

    public function countByNumero(string $numero) {
        $queryBuilder = $this
            ->createQueryBuilder('livraison')
            ->select('COUNT(livraison.id) AS counter')
            ->where('livraison.numero = :numero')
            ->setParameter('numero', $numero . '%');

        $result = $queryBuilder
            ->getQuery()
            ->getResult();

        return !empty($result) ? ($result[0]['counter'] ?? 0) : 0;
    }

    public function getNumeroLivraisonGroupByDemande (array $demandes)
    {
        $queryBuilder = $this->createQueryBuilder('livraison')
            ->select('demande.id AS demandeId')
            ->addSelect('livraison.numero AS numeroLivraison')
            ->join('livraison.preparation', 'preparation')
            ->join('preparation.demande', 'demande')
            ->where('preparation.demande in (:demandes)')
            ->setParameter('demandes', $demandes);

        $result = $queryBuilder->getQuery()->execute();
        return array_reduce($result, function (array $carry, $current) {

            $demandeId = $current['demandeId'];
            $numeroLivraison = $current['numeroLivraison'];
            if (!isset($carry[$demandeId])) {
                $carry[$demandeId] = [];
            }
            $carry[$demandeId][] = $numeroLivraison;
            return $carry;
        }, []);
    }

    /**
     * @param array|null $statuses
     * @return int|mixed|string
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    public function countByTypesAndStatuses(?array $types,
                                            ?array $statuses,
                                            $displayDeliveryOrderContentCheckbox,
                                            $displayDeliveryOrderContent,
                                            $displayDeliveryOrderWithExpectedDate) {
        if (!empty($types) && !empty($statuses)) {
            $qb = $this->createQueryBuilder('deliveryOrder')
                ->select('COUNT(DISTINCT deliveryOrder.id) as count')
                ->leftJoin('deliveryOrder.statut', 'status')
                ->leftJoin('deliveryOrder.preparation', 'preparation')
                ->leftJoin('preparation.demande', 'request')
                ->leftJoin('request.type', 'type')
                ->where('status IN (:statuses)')
                ->andWhere('type IN (:types)')
                ->setParameter('statuses', $statuses)
                ->setParameter('types', $types);
            if ($displayDeliveryOrderWithExpectedDate) {
                $qb
                    ->andWhere('preparation.expectedAt IS NOT NULL');
            }
            if ($displayDeliveryOrderContentCheckbox) {
                if ($displayDeliveryOrderContent === 'displayLogisticUnitsCount') {
                    $qb
                        ->leftJoin('preparation.articleLines', 'article_lines')
                        ->leftJoin('article_lines.pack', 'pack')
                        ->addSelect('COUNT(DISTINCT pack.id) as sub');
                } else if ($displayDeliveryOrderContent === 'displayArticlesCount') {
                    $qb
                        ->leftJoin('preparation.articleLines', 'article_lines')
                        ->addSelect('COUNT(DISTINCT article_lines.id) as sub');
                }
            }
            return $qb
                ->getQuery()
                ->getResult();

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
                ->createQueryBuilder('deliveryOrder')
                ->select('deliveryOrder.date AS date')
                ->innerJoin('deliveryOrder.statut', 'status')
                ->innerJoin('deliveryOrder.preparation', 'preparation')
                ->innerJoin('preparation.demande', 'deliveryRequest')
                ->innerJoin('deliveryRequest.type', 'type')
                ->andWhere('status IN (:statuses)')
                ->andWhere('type IN (:types)')
                ->andWhere('status.state IN (:treatedStates)')
                ->addOrderBy('deliveryOrder.date', 'ASC')
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

    public function countByDates(DateTime $dateMin,
                                 DateTime $dateMax,
                                 array $options): int {
        $deliveryOrderStatusesFilter = $options['deliveryOrderStatusesFilter'] ?? [];
        $deliveryOrderTypesFilter = $options['deliveryOrderTypesFilter'] ?? [];

        $date = match ($options['date']) {
            "treatmentDate" => "delivery_order.dateFin",
            "expectedDate" => "join_delivery_request.expectedAt",
            default => "delivery_order.date"
        };

        $qb = $this->createQueryBuilder('delivery_order')
            ->select('COUNT(delivery_order)')
            ->andWhere("$date BETWEEN :dateMin AND :dateMax")
            ->setParameters([
                'dateMin' => $dateMin,
                'dateMax' => $dateMax
            ]);

        if (!empty($deliveryOrderStatusesFilter)) {
            $qb
                ->andWhere('delivery_order.statut IN (:deliveryOrderStatusesFilter)')
                ->setParameter('deliveryOrderStatusesFilter', $deliveryOrderStatusesFilter);
        }

        if (!empty($deliveryOrderTypesFilter)) {
            $qb
                ->join('delivery_order.preparation', 'join_preparation')
                ->join('join_preparation.demande', 'join_delivery_request')
                ->andWhere('join_delivery_request.type IN (:deliveryOrderTypesFilter)')
                ->setParameter('deliveryOrderTypesFilter', $deliveryOrderTypesFilter);
        }

        return $qb
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countContentByDates(DateTime $dateMin,
                                        DateTime $dateMax,
                                        array $deliveryOrderStatusesFilter,
                                        array $deliveryOrderTypesFilter,
                                        array $options = []): int {
        $date = match ($options['date']) {
            "treatmentDate" => "delivery_order.dateFin",
            "expectedDate" => "join_delivery_request.expectedAt",
            default => "delivery_order.date"
        };

        $queryBuilder = $this->createQueryBuilder('delivery_order')
            ->join('delivery_order.preparation', 'join_preparation')
            ->join('join_preparation.articleLines', 'join_article_lines')
            ->join('join_article_lines.article', 'join_article')
            ->join('join_article.articleFournisseur', 'join_provider_article')
            ->join('join_provider_article.referenceArticle', 'join_article_reference')
            ->andWhere("$date BETWEEN :dateMin AND :dateMax")
            ->setParameters([
                'dateMin' => $dateMin,
                'dateMax' => $dateMax
            ]);

        if($options['displayDeliveryOrderContent'] === 'displayLogisticUnitsCount') {
            $queryBuilder
                ->select('COUNT(DISTINCT(join_article_lines.pack)) AS count');
        } else {
            $queryBuilder
                ->select('COUNT(join_article.id) AS count')
                ->andWhere('join_article_reference.typeQuantite = :quantity_type')
                ->setParameter('quantity_type', ReferenceArticle::QUANTITY_TYPE_ARTICLE);
        }

        if (!empty($deliveryOrderStatusesFilter)) {
            $queryBuilder
                ->andWhere('delivery_order.statut IN (:deliveryOrderStatusesFilter)')
                ->setParameter('deliveryOrderStatusesFilter', $deliveryOrderStatusesFilter);
        }

        if (!empty($deliveryOrderTypesFilter)) {
            $queryBuilder
                ->join('join_preparation.demande', 'join_delivery_request')
                ->andWhere('join_delivery_request.type IN (:deliveryOrderTypesFilter)')
                ->setParameter('deliveryOrderTypesFilter', $deliveryOrderTypesFilter);
        }

        return $queryBuilder
            ->getQuery()
            ->getSingleScalarResult();
    }
}
