<?php

namespace App\Repository;

use App\Entity\Arrivage;
use App\Entity\FiltreSup;
use App\Entity\FreeField;
use App\Entity\Statut;
use App\Helper\QueryCounter;
use App\Service\VisibleColumnService;
use DateTime;
use Doctrine\ORM\EntityRepository;
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

    public function findByParamsAndFilters(InputBag $params, array $filters, VisibleColumnService $visibleColumnService, array $options = []): array
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

        $total = QueryCounter::count($qb, 'arrival');

        // filtres sup
        foreach ($filters as $filter) {
            switch ($filter['field']) {
                case 'statut':
					$value = explode(',', $filter['value']);
					$qb
						->join('arrival.statut', 's')
						->andWhere('s.id in (:statut)')
						->setParameter('statut', $value);
					break;
                case 'utilisateurs':
                    $value = explode(',', $filter['value']);
                    $qb
                        ->join('arrival.destinataire', 'dest')
                        ->andWhere("dest.id in (:userId)")
                        ->setParameter('userId', $value);
                    break;
                case 'providers':
                    $value = explode(',', $filter['value']);
					$qb
                        ->join('arrival.fournisseur', 'f2')
                        ->andWhere("f2.id in (:fournisseurId)")
                        ->setParameter('fournisseurId', $value);
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
                        $qb
                            ->andWhere('arrival.customs = :value')
                            ->setParameter('value', $filter['value']);
                    }
                    break;
                case 'frozen':
                    if ($filter['value'] === '1') {
                        $qb
                            ->andWhere('arrival.frozen = :value')
                            ->setParameter('value', $filter['value']);
                    }
                    break;
                case FiltreSup::FIELD_BUSINESS_UNIT:
                    $values = Stream::explode(",", $filter['value'])
                        ->map(fn(string $value) => strtok($value, ':'))
                        ->toArray();
                    $qb
                        ->andWhere("arrival.businessUnit IN (:values)")
                        ->setParameter('values', $values);
                    break;
                case 'numArrivage':
                    $qb
                        ->andWhere('arrival.numeroArrivage = :numeroArrivage')
                        ->setParameter('numeroArrivage', $filter['value']);
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
                        "receiver" => "search_receivers.username LIKE :search_value",
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
                    ];

                    $visibleColumnService->bindSearchableColumns($conditions, 'arrival', $qb, $options['user'], $search);

                    $qb
                        ->leftJoin('arrival.transporteur', 'search_carrier')
                        ->leftJoin('arrival.chauffeur', 'search_driver')
                        ->leftJoin('arrival.fournisseur', 'search_provider')
                        ->leftJoin('arrival.destinataire', 'search_receivers')
                        ->leftJoin('arrival.acheteurs', 'search_buyers')
                        ->leftJoin('arrival.utilisateur', 'search_user')
                        ->leftJoin('arrival.type', 'search_type')
                        ->leftJoin('arrival.statut', 'search_status')
                        ->leftJoin('arrival.dropLocation', 'search_dropLocation');
                }
            }

            $filtered = QueryCounter::count($qb, 'arrival');

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
                        $qb
                            ->leftJoin('arrival.type', 'order_type')
                            ->orderBy('order_type.label', $order);
                    } else if ($column === 'provider') {
                        $qb
                            ->leftJoin('arrival.fournisseur', 'order_fournisseur')
                            ->orderBy('order_fournisseur.nom', $order);
                    } else if ($column === 'receiver') {
                        $qb
                            ->leftJoin('arrival.destinataire', 'a2')
                            ->orderBy('a2.username', $order);
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
                        $qb
                            ->leftJoin('arrival.statut', 'order_status')
                            ->orderBy('order_status.nom', $order);
                    } else if ($column === 'dropLocation') {
                        $qb
                            ->leftJoin('arrival.dropLocation', 'order_dropLocation')
                            ->orderBy('order_dropLocation.label', $order);
                    } else {
                        $freeFieldId = VisibleColumnService::extractFreeFieldId($column);
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

    public function countByUser($user)
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
        /** @lang DQL */
            "SELECT COUNT(a)
            FROM App\Entity\Arrivage a
            WHERE a.utilisateur = :user OR a.destinataire = :user"
        )->setParameter('user', $user);

        return $query->getSingleScalarResult();
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
}
