<?php

namespace App\Repository;

use App\Entity\Arrivage;
use App\Entity\Emplacement;
use App\Entity\FiltreSup;
use App\Entity\FreeField\FreeField;
use App\Entity\Language;
use App\Entity\Statut;
use App\Entity\Tracking\TrackingMovement;
use App\Helper\QueryBuilderHelper;
use App\Service\FieldModesService;
use DateTime;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\HttpFoundation\InputBag;
use WiiCommon\Helper\Stream;

/**
 * @method Arrivage|null find($id, $lockMode = null, $lockVersion = null)
 * @method Arrivage|null findOneBy(array $criteria, array $orderBy = null)
 * @method Arrivage[]    findAll()
 * @method Arrivage[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ArrivageRepository extends EntityRepository
{
    private const DtToDbLabels = [
        'creationDate' => 'date',
        'arrivalNumber' => 'numeroArrivage',
        'carrier' => 'carrier',
        'driver' => 'driver',
        'trackingCarrierNumber' => 'noTracking',
        'orderNumber' => 'numeroCommandeList',
        'provider' => 'provider',
        'receiver' => 'receiver',
        'buyers' => 'buyers',
        'nbUm' => 'nbUm',
        'status' => 'status',
        'user' => 'user',
        'type' => 'type',
        'custom' => 'customs',
        'frozen' => 'frozen',
        'emergency' => 'isUrgent',
        'projectNumber' => 'projectNumber',
        'businessUnit' => 'businessUnit'
    ];

    public function countByDates(DateTime $dateMin,
                                 DateTime $dateMax,
                                 array $arrivalStatusesFilter = [],
                                 array $arrivalTypesFilter = []): int
    {
		$queryBuilder = $this->createQueryBuilderByDates($dateMin, $dateMax)
            ->select('COUNT(arrivage)');

        if (!empty($arrivalStatusesFilter)) {
            $queryBuilder
                ->andWhere('arrivage.statut IN (:arrivalStatuses)')
                ->setParameter('arrivalStatuses', $arrivalStatusesFilter);
        }

        if (!empty($arrivalTypesFilter)) {
            $queryBuilder
                ->andWhere('arrivage.type IN (:arrivalTypes)')
                ->setParameter('arrivalTypes', $arrivalTypesFilter);
        }

        return $queryBuilder
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countByDate(DateTime $date): ?int
    {
		return $this->createQueryBuilder('arrivage')
            ->select('COUNT(arrivage)')
            ->where('arrivage.date = :date')
            ->setParameter('date', $date)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return Arrivage[]|null
     */
    public function findByDates(DateTime $dateMin, DateTime $dateMax): ?array
    {
		return $this->createQueryBuilderByDates($dateMin, $dateMax)
            ->getQuery()
            ->execute();
    }

    public function createQueryBuilderByDates(DateTime $dateMin, DateTime $dateMax): QueryBuilder
    {
        return $this->createQueryBuilder('arrivage')
            ->where('arrivage.date BETWEEN :dateMin AND :dateMax')
            ->setParameters([
                'dateMin' => $dateMin,
                'dateMax' => $dateMax
            ]);
    }

    public function countByChauffeur($chauffeur)
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
        /** @lang DQL */
            "SELECT COUNT(a)
			FROM App\Entity\Arrivage a
			WHERE a.chauffeur = :chauffeur"
        )->setParameter('chauffeur', $chauffeur);

        return $query->getSingleScalarResult();
    }

    public function countUnsolvedDisputesByArrivage($arrivage)
    {
        return $this->createQueryBuilder('arrival')
            ->select('COUNT(dispute.id)')
            ->join('arrival.packs', 'pack')
            ->join('pack.disputes', 'dispute')
            ->join('dispute.status', 'status')
            ->andWhere('pack.arrivage = :arrival')
            ->andWhere('status.state = :stateNotTreated')
            ->setMaxResults(1)
            ->setParameter('stateNotTreated', Statut::NOT_TREATED)
            ->setParameter('arrival', $arrivage)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countByDays($firstDay, $lastDay)
    {
        $from = new DateTime(str_replace("/", "-", $firstDay) . " 00:00:00");
        $to = new DateTime(str_replace("/", "-", $lastDay) . " 23:59:59");
        $em = $this->getEntityManager();
        $query = $em->createQuery(
            "SELECT COUNT(a) as count, a.date as date
                FROM App\Entity\Arrivage a
                WHERE a.date BETWEEN :firstDay AND :lastDay
                GROUP BY a.date"
        )->setParameters([
            'lastDay' => $to,
            'firstDay' => $from
        ]);
        return $query->execute();
    }

    public function findByParamsAndFilters(InputBag $params, array $filters, FieldModesService $fieldModesService, array $options = []): array
    {
        $qb = $this->createQueryBuilder("arrival")
            ->addSelect('SUM(main_packs.weight) AS totalWeight')
            ->addSelect('COUNT(main_packs.id) AS packsCount')
            ->addSelect('COUNT(main_dispatch_packs.id) AS dispatchedPacksCount')
            ->leftJoin('arrival.packs', 'main_packs')
            ->leftJoin('main_packs.dispatchPacks', 'main_dispatch_packs')
            ->groupBy('arrival');

        // filtre arrivages de l'utilisateur
        if ($options['userIdArrivalFilter']) {
            $qb
                ->join('arrival.acheteurs', 'ach')
                ->where('ach.id = :userId')
                ->setParameter('userId', $options['user']->getId());
        }

        $total = QueryBuilderHelper::count($qb, 'arrival');

        // filtres sup
        foreach ($filters as $filter) {
            switch ($filter['field']) {
                case 'type':
					$qb
						->join('arrival.type', 'filter_type')
						->andWhere('filter_type.label = :type')
						->setParameter('type', $filter['value']);
					break;
                case 'statut':
					$value = explode(',', $filter['value']);
					$qb
						->join('arrival.statut', 'filter_status')
						->andWhere('filter_status.id in (:statut)')
						->setParameter('statut', $value);
					break;
                case 'utilisateurs':
                    $value = explode(',', $filter['value']);
                    $qb
                        ->innerJoin('arrival.receivers', 'filter_receivers')
                        ->andWhere('filter_receivers.id IN (:userId)')
                        ->setParameter('userId', $value);
                    break;
                case 'providers':
                    $value = explode(',', $filter['value']);
					$qb
                        ->join('arrival.fournisseur', 'f2')
                        ->andWhere("f2.id in (:fournisseurId)")
                        ->setParameter('fournisseurId', $value);
                    break;
                case 'commandList':
                    $values = Stream::explode(',', $filter['value'])
                        ->filter()
                        ->map(static fn(string $value) => explode(':', $value)[0])
                        ->toArray();

                    if (!empty($values)) {
                        $expr = $qb->expr()->orX();
                        foreach ($values as $value) {
                            $keyParameter = "search_order_number_{$expr->count()}";
                            $expr->add("JSON_CONTAINS(arrival.numeroCommandeList, :$keyParameter, '$') = true");
                            $qb->setParameter($keyParameter, "\"$value\"");
                        }

                        $qb->andWhere($expr);
                    }
                    break;
                case 'emplacement':
                    $value = explode(',', $filter['value']);
                    $qb
                        ->join('arrival.dropLocation', 'filter_drop_location')
                        ->andWhere("filter_drop_location.id in (:locationId)")
                        ->setParameter('locationId', $value);
                    break;
                case 'carriers':
                    $value = explode(',', $filter['value']);
                    $qb
                        ->join('arrival.transporteur', 't2')
                        ->andWhere("t2.id in (:transporteurId)")
                        ->setParameter('transporteurId', $value);
                    break;
                case 'dateMin':
                    $qb
                        ->andWhere('arrival.date >= :dateMin')
                        ->setParameter('dateMin', $filter['value'] . " 00:00:00");
                    break;
                case 'dateMax':
                    $qb
                        ->andWhere('arrival.date <= :dateMax')
                        ->setParameter('dateMax', $filter['value'] . " 23:59:59");
                    break;
                case 'emergency':
                    $qb
                        ->andWhere('arrival.isUrgent = :isUrgent')
                        ->setParameter('isUrgent', $filter['value']);
                    break;
                case 'customs':
                    if ($filter['value'] === '1') {
                        $qb->andWhere('arrival.customs = 1');
                    }
                    break;
                case 'frozen':
                    if ($filter['value'] === '1') {
                        $qb->andWhere('arrival.frozen = 1');
                    }
                    break;
                case FiltreSup::FIELD_BUSINESS_UNIT:
                    $values = Stream::explode(",", $filter['value'])
                        ->filter()
                        ->map(fn(string $value) => strtok($value, ':'))
                        ->toArray();
                    $qb
                        ->andWhere("arrival.businessUnit IN (:businessUnit)")
                        ->setParameter('businessUnit', $values);
                    break;
                case FiltreSup::FIELD_PROJECT_NUMBER:
                    $value = $filter['value'];
                    $qb
                        ->andWhere("arrival.projectNumber LIKE :projectNumber")
                        ->setParameter('projectNumber', "%$value%");
                    break;
                case 'numArrivage':
                    $qb
                        ->andWhere('arrival.numeroArrivage = :numeroArrivage')
                        ->setParameter('numeroArrivage', $filter['value']);
                    break;
                case 'numTruckArrival':
                    $qb
                        ->leftJoin('arrival.truckArrivalLines', 'lines')
                        ->leftJoin('lines.truckArrival', 'filter_truckArrival')
                        ->leftJoin('arrival.truckArrival', 'filter_arrival_truckArrival')
                        ->andWhere($qb->expr()->orX(
                            'filter_truckArrival.number LIKE :numTruckArrival',
                            'filter_arrival_truckArrival.number LIKE :numTruckArrival'
                        ))
                        ->setParameter('numTruckArrival' , '%'.$filter['value'].'%');
                    break;
                case 'noTracking':
                    $qb
                        ->leftJoin('arrival.truckArrivalLines', 'truckArrivalLines')
                        ->andWhere('arrival.noTracking LIKE :noTracking OR truckArrivalLines.number LIKE :noTracking')
                        ->setParameter('noTracking', '%'.$filter['value'].'%');
                    break;
            }
        }

		//Filter search
        if (!empty($params)) {
            if (!empty($params->all('search'))) {
                $search = $params->all('search')['value'];
                if (!empty($search)) {
                    $conditions = [
                        "creationDate" => "DATE_FORMAT(arrival.date, '%d/%m/%Y') LIKE :search_value",
                        "arrivalNumber" => "arrival.numeroArrivage LIKE :search_value",
                        "carrier" => "search_carrier.label LIKE :search_value",
                        "driver" => "search_driver.prenom LIKE :search_value OR search_driver.nom LIKE :search_value",
                        "trackingCarrierNumber" => "arrival.noTracking LIKE :search_value",
                        "orderNumber" => "arrival.numeroCommandeList LIKE :search_value",
                        "type" => "search_type.label LIKE :search_value",
                        "provider" => "search_provider.nom LIKE :search_value",
                        "receivers" => "search_receivers.username LIKE :search_value",
                        "buyers" => "search_buyers.username LIKE :search_value",
                        "nbUm" => null,
                        "customs" => null,
                        "frozen" => null,
                        "status" => "search_status.nom LIKE :search_value",
                        "user" => "search_user.username LIKE :search_value",
                        "emergency" => null,
                        "projectNumber" => "arrival.projectNumber LIKE :search_value",
                        "businessUnit" => "arrival.businessUnit LIKE :search_value",
                        "dropLocation" => "search_dropLocation.label LIKE :search_value",
                        "truckArrivalNumber" => "search_truckArrival.number LIKE :search_value",
                    ];

                    $fieldModesService->bindSearchableColumns($conditions, 'arrival', $qb, $options['user'], $search);

                    $qb
                        ->leftJoin('arrival.transporteur', 'search_carrier')
                        ->leftJoin('arrival.chauffeur', 'search_driver')
                        ->leftJoin('arrival.fournisseur', 'search_provider')
                        ->leftJoin('arrival.receivers', 'search_receivers')
                        ->leftJoin('arrival.acheteurs', 'search_buyers')
                        ->leftJoin('arrival.utilisateur', 'search_user')
                        ->leftJoin('arrival.type', 'search_type')
                        ->leftJoin('arrival.statut', 'search_status')
                        ->leftJoin('arrival.dropLocation', 'search_dropLocation')
                        ->leftJoin('arrival.truckArrivalLines', 'search_truckArrival');
                }
            }

            $filtered = QueryBuilderHelper::count($qb, 'arrival');

            if (!empty($params->all('order'))) {
                $order = $params->all('order')[0]['dir'];
                if (!empty($order)) {
                    $orderData = $params->all('columns')[$params->all('order')[0]['column']]['data'];
                    $column = self::DtToDbLabels[$orderData] ?? $orderData;

                    if ($column === 'carrier') {
                        $qb
                            ->leftJoin('arrival.transporteur', 't2')
                            ->orderBy('t2.label', $order);
                    } else if ($column === 'driver') {
                        $qb
                            ->leftJoin('arrival.chauffeur', 'c2')
                            ->orderBy('c2.nom', $order);
                    } else if ($column === 'type') {
                        $qb = QueryBuilderHelper::joinTranslations($qb, $options['language'], $options['defaultLanguage'], ['type'], ["order" => $order]);
                    } else if ($column === 'provider') {
                        $qb
                            ->leftJoin('arrival.fournisseur', 'order_fournisseur')
                            ->orderBy('order_fournisseur.nom', $order);
                    } else if ($column === 'receivers') {
                        $qb
                            ->leftJoin('arrival.receivers', 'order_receivers')
                            ->orderBy('order_receivers.username', $order)
                            ->groupBy('arrival.id, order_receivers.username');
                    } else if ($column === 'buyers') {
                        $qb
                            ->leftJoin('arrival.acheteurs', 'ach2')
                            ->orderBy('ach2.username', $order)
                            ->groupBy('arrival.id, ach2.username');
                    } else if ($column === 'user') {
                        $qb
                            ->leftJoin('arrival.utilisateur', 'u2')
                            ->orderBy('u2.username', $order);
                    } else if ($column === 'nbUm') {
                        $qb->orderBy('packsCount', $order);
                    } else if ($column === 'totalWeight') {
                        $qb->orderBy('totalWeight', $order);
                    } else if ($column === 'status') {
                        $qb = QueryBuilderHelper::joinTranslations($qb, $options['language'], $options['defaultLanguage'], ['statut'], ["order" => $order]);
                    } else if ($column === 'dropLocation') {
                        $qb
                            ->leftJoin('arrival.dropLocation', 'order_dropLocation')
                            ->orderBy('order_dropLocation.label', $order);
                    } else {
                        $freeFieldId = FieldModesService::extractFreeFieldId($column);
                        if(is_numeric($freeFieldId)) {
                            /** @var FreeField $freeField */
                            $freeField = $this->getEntityManager()->getRepository(FreeField::class)->find($freeFieldId);
                            if($freeField->getTypage() === FreeField::TYPE_NUMBER) {
                                $qb->orderBy("CAST(JSON_EXTRACT(arrival.freeFields, '$.\"$freeFieldId\"') AS SIGNED)", $order);
                            } else {
                                $qb->orderBy("JSON_EXTRACT(arrival.freeFields, '$.\"$freeFieldId\"')", $order);
                            }
                        } else if (property_exists(Arrivage::class, $column)) {
                            $qb->orderBy("arrival.$column", $order);
                        }
                    }
                }
            }
        }

        if(!$params->has("order")) {
            $qb->addOrderBy("arrival.date", "DESC");
        }

        if (!empty($params)) {
            if ($params->getInt('start')) {
                $qb->setFirstResult($params->getInt('start'));
            }

            $pageLength = $params->getInt('length') ? $params->getInt('length') : 100;
            if ($pageLength) {
                $qb->setMaxResults($pageLength);
            }
        }

        if($options['dispatchMode']) {
            $qb->orderBy("arrival.date", "DESC");
        }

        return [
            'data' => $qb->getQuery()->getResult(),
            'count' => $filtered,
            'total' => $total
        ];
    }

    public function countByUser($user): int {
        $qb = $this->createQueryBuilder("arrival");

        $qb
            ->select("COUNT(arrival)")
            ->andWhere($qb->expr()->orX(
                "arrival.utilisateur = :user",
                ":user MEMBER OF arrival.receivers"
            ))
            ->setParameter('user', $user);

        return $qb
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function getTotalWeightByArrivals(DateTime $from, DateTime $to): ?array {
        $queryBuilder = $this->createQueryBuilder("arrival");
        $expr = $queryBuilder->expr();

        $result = $queryBuilder
            ->select("arrival.id AS id")
            ->addSelect("SUM(packs.weight) AS totalWeight")
            ->leftJoin("arrival.packs", "packs")
            ->andWhere($expr->between('arrival.date', ':dateFrom', ':dateTo'))
            ->groupBy("arrival")
            ->setParameter('dateFrom', $from)
            ->setParameter('dateTo', $to)
            ->getQuery()
            ->getResult();

        return Stream::from($result)
            ->keymap(fn(array $arrival) => [$arrival['id'], $arrival['totalWeight']])
            ->toArray();
    }

    public function countArrivalPacksInDispatch(Arrivage $arrival): int {
        return $this->createQueryBuilder('arrival')
            ->select("COUNT(dispatch_packs)")
            ->leftJoin('arrival.packs', 'packs')
            ->leftJoin('packs.dispatchPacks', 'dispatch_packs')
            ->where('arrival.id = :arrival')
            ->setParameter('arrival', $arrival)
            ->setMaxResults(1)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function iterateArrivals(DateTime $dateMin, DateTime $dateMax): iterable {
        $qb = $this->createQueryBuilder('arrivage')
            ->andWhere('arrivage.date BETWEEN :dateMin AND :dateMax')
            ->setParameter('dateMin', $dateMin)
            ->setParameter('dateMax', $dateMax);
        return $qb
            ->getQuery()
            ->toIterable();
    }

    public function getForSelect(?string $term, Language $language, Language $default): array {
        return $this->createQueryBuilder('nature')
            ->select("nature.id AS id")
            ->addSelect("IFNULL(join_translation.translation, IFNULL(join_translation_default.translation, nature.label)) AS text")
            ->leftJoin("nature.labelTranslation", "join_labelTranslation")
            ->leftJoin("join_labelTranslation.translations", "join_translation", Join::WITH, "join_translation.language = :language")
            ->leftJoin("join_labelTranslation.translations", "join_translation_default", Join::WITH, "join_translation_default.language = :default")
            ->andWhere("IFNULL(join_translation.translation, IFNULL(join_translation_default.translation, nature.label)) LIKE :term")
            ->setParameter("term", "%$term%")
            ->setParameter("language", $language)
            ->setParameter("default", $default)
            ->getQuery()
            ->getResult();
    }

    public function countByLocation(Emplacement $dropLocation): int {
        return $this->createQueryBuilder("arrival")
            ->select("COUNT(arrival.id)")
            ->innerJoin("arrival.dropLocation", "join_dropLocation", Join::WITH, "join_dropLocation.id = :dropLocation")
            ->setParameter("dropLocation", $dropLocation->getId())
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Counts the number of Arrivage older than the given date.
     */
    public function countOlderThan(DateTime $date): int {
        return $this->createQueryBuilderOlderThan('arrival', $date)
            ->select('COUNT(arrival.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Returns an iterable of Arrivage older than the given date
     * @return iterable<Arrivage>
     */
    public function iterateOlderThan(DateTime $date): iterable {
        return $this->createQueryBuilderOlderThan('arrival', $date)
            ->getQuery()
            ->toIterable();
    }

    public function createQueryBuilderOlderThan(string $alias,
                                                DateTime $date): QueryBuilder {
        return $this->createQueryBuilder($alias)
            ->andWhere('$alias.date < :date')
            ->setParameter('date', $date);
    }
}
