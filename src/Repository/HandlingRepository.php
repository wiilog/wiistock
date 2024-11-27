<?php

namespace App\Repository;

use App\Entity\AverageRequestTime;
use App\Entity\FiltreSup;
use App\Entity\Handling;
use App\Entity\Statut;
use App\Entity\Utilisateur;
use App\Helper\QueryBuilderHelper;
use Symfony\Component\HttpFoundation\InputBag;
use WiiCommon\Helper\Stream;
use DateTime;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\Query\Expr\Join;

/**
 * @method Handling|null find($id, $lockMode = null, $lockVersion = null)
 * @method Handling|null findOneBy(array $criteria, array $orderBy = null)
 * @method Handling[]    findAll()
 * @method Handling[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class HandlingRepository extends EntityRepository
{

    private const DtToDbLabels = [
        'number' => 'number',
        'creationDate' => 'creationDate',
        'type' => 'type',
        'requester' => 'requester',
        'subject' => 'subject',
        'desiredDate' => 'desiredDate',
        'validationDate' => 'validationDate',
        'status' => 'status',
        'emergency' => 'emergency',
        'treatedBy' => 'treatedBy',
        'treatmentDelay' => 'treatmentDelay',
        'carriedOutOperationCount' => 'carriedOutOperationCount'
    ];

    /**
     * @return int|mixed|string
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    public function countHandlingToTreat(){

        $qb = $this->createQueryBuilder('handling');

        $qb->select('COUNT(handling)')
            ->join('handling.status', 'status')
            ->where('status.state = :notTreatedId')
            ->setParameter('notTreatedId', Statut::NOT_TREATED);

        return $qb
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @param int[] $typeIds
     * @param callable $customFieldsFactory
     * @return int|mixed|string
     */
    public function getMobileHandlingsByUserTypes(array $typeIds) {

        $queryBuilder = $this->createQueryBuilder('handling');
        $queryBuilder
            ->select('handling.id AS id')
            ->addSelect('handling.desiredDate AS desiredDate')
            ->addSelect('handling.comment AS comment')
            ->addSelect('(CASE WHEN triggeringSensorWrapper.id IS NOT NULL THEN triggeringSensorWrapper.name ELSE handling_requester.username END) as requester')
            ->addSelect('handling.source AS source')
            ->addSelect('handling.destination AS destination')
            ->addSelect('handling.subject AS subject')
            ->addSelect('handling.number AS number')
            ->addSelect('handling_type.label AS typeLabel')
            ->addSelect('handling_type.id AS typeId')
            ->addSelect('handling.emergency AS emergency')
            ->addSelect('handling.carriedOutOperationCount AS carriedOutOperationCount')
            ->addSelect('handling.freeFields AS freeFields')
            ->addSelect('status.id AS statusId')
            ->leftJoin('handling.requester', 'handling_requester')
            ->leftJoin('handling.triggeringSensorWrapper', 'triggeringSensorWrapper')
            ->leftJoin('handling.status', 'status')
            ->leftJoin('handling.type', 'handling_type')
            ->andWhere('status.needsMobileSync = true')
            ->andWhere('status.state != :treatedId')
            ->andWhere('handling_type.id IN (:userTypes)')
            ->setParameter('userTypes', $typeIds)
            ->setParameter('treatedId', Statut::TREATED);

        return $queryBuilder->getQuery()->getResult();
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
            "SELECT COUNT(handling)
            FROM App\Entity\Handling handling
            WHERE handling.requester = :user"
		)->setParameter('user', $user);

		return $query->getSingleScalarResult();
	}

    /**
     * @param DateTime $dateMin
     * @param DateTime $dateMax
     * @return iterable|mixed[] $queryBuilder
     */
    public function iterateByDates($dateMin, $dateMax) {
        $dateMax = $dateMax->format('Y-m-d H:i:s');
        $dateMin = $dateMin->format('Y-m-d H:i:s');

        return $queryBuilder = $this->createQueryBuilder('handling')
            ->select('handling')
            ->andWhere('handling.creationDate BETWEEN :dateMin AND :dateMax')
            ->setParameters([
                'dateMin' => $dateMin,
                'dateMax' => $dateMax,
            ])
            ->getQuery()
            ->toIterable();
    }


    public function findByParamAndFilters(InputBag $params, $filters, Utilisateur $user, $selectedDate = null, array $options = []): array
    {
        $qb = $this->createQueryBuilder('handling')
            ->groupBy('handling.id');

        if(!empty($user->getHandlingTypeIds())){
            $qb
                ->join('handling.type', 'join_type')
                ->andWhere('join_type.id IN (:userHandlingTypeIds)')
                ->setParameter('userHandlingTypeIds', $user->getHandlingTypeIds());
        }

        if ($selectedDate) {
            $qb->andWhere('handling.desiredDate >= :filter_dateMin_value')
                ->setParameter('filter_dateMin_value', "$selectedDate 00:00:00")
                ->andWhere('handling.desiredDate <= :filter_dateMax_value')
                ->setParameter('filter_dateMax_value', "$selectedDate 23:59:59");
        }

        $countTotal = QueryBuilderHelper::count($qb, 'handling');

        if (!$selectedDate) {
            // filtres sup
            foreach ($filters as $filter) {
                switch ($filter['field']) {
                    case 'statuses-filter':
                        if ($filter["value"]) {
                            $value = explode(",", $filter["value"]);
                            $qb->join('handling.status', 'filter_status')
                                ->andWhere('filter_status.id in (:filter_status_value)')
                                ->setParameter('filter_status_value', $value);
                        }
                        break;
                    case 'utilisateurs':
                        $value = explode(',', $filter['value']);
                        $qb
                            ->join('handling.requester', 'filter_requester')
                            ->andWhere("filter_requester.id in (:filter_requester_username_value)")
                            ->setParameter('filter_requester_username_value', $value);
                        break;
                    case FiltreSup::FIELD_MULTIPLE_TYPES:
                        if(!empty($filter['value'])){
                            $value = Stream::explode(',', $filter['value'])
                                ->filter()
                                ->map(static fn($type) => explode(':', $type)[0])
                                ->toArray();

                            $qb
                                ->join('handling.type', 'filter_type')
                                ->andWhere('filter_type.id IN (:filter_type_value)')
                                ->setParameter('filter_type_value', $value);
                        }
                        break;
                    case 'emergencyMultiple':
                        $value = array_map(function ($value) {
                            return explode(":", $value)[0];
                        }, explode(',', $filter['value']));
                        $qb
                            ->andWhere("handling.emergency in (:filter_emergency_value)")
                            ->setParameter('filter_emergency_value', $value);
                        break;
                    case 'date-choice_creationDate':
                        foreach ($filters as $filter) {
                            switch ($filter['field']) {
                                case 'dateMin':
                                    $qb->andWhere('handling.creationDate >= :filter_dateMin_value')
                                        ->setParameter('filter_dateMin_value', $filter['value'] . " 00:00:00");
                                    break;
                                case 'dateMax':
                                    $qb->andWhere('handling.creationDate <= :filter_dateMax_value')
                                        ->setParameter('filter_dateMax_value', $filter['value'] . " 23:59:59");
                                    break;
                            }
                        }
                        break;
                    case 'date-choice_expectedDate':
                        foreach ($filters as $filter) {
                            switch ($filter['field']) {
                                case 'dateMin':
                                    $qb->andWhere('handling.desiredDate >= :filter_dateMin_value')
                                        ->setParameter('filter_dateMin_value', $filter['value'] . " 00:00:00");
                                    break;
                                case 'dateMax':
                                    $qb->andWhere('handling.desiredDate <= :filter_dateMax_value')
                                        ->setParameter('filter_dateMax_value', $filter['value'] . " 23:59:59");
                                    break;
                            }
                        }
                        break;
                    case 'date-choice_treatmentDate':
                        foreach ($filters as $filter) {
                            switch ($filter['field']) {
                                case 'dateMin':
                                    $qb->andWhere('handling.validationDate >= :filter_dateMin_value')
                                        ->setParameter('filter_dateMin_value', $filter['value'] . " 00:00:00");
                                    break;
                                case 'dateMax':
                                    $qb->andWhere('handling.validationDate <= :filter_dateMax_value')
                                        ->setParameter('filter_dateMax_value', $filter['value'] . " 23:59:59");
                                    break;
                            }
                        }
                        break;
                    case 'subject':
                        $qb->andWhere('handling.subject LIKE :filter_subject')
                            ->setParameter('filter_subject', "%{$filter['value']}%");
                        break;
                    case 'receivers':
                        $value = explode(',', $filter['value']);
                        $qb
                            ->join('handling.receivers', 'filter_receivers')
                            ->andWhere("filter_receivers.id in (:filter_receivers_username_value)")
                            ->setParameter('filter_receivers_username_value', $value);
                        break;
                }
            }

            //Filter search
            if (!empty($params)) {
                if (!empty($params->all('search'))) {
                    $search = $params->all('search')['value'];
                    if (!empty($search)) {
                        $qb
                            ->leftJoin("handling.type", 'search_type')
                            ->leftJoin("handling.requester", 'search_requester')
                            ->leftJoin("handling.status", 'search_status')
                            ->leftJoin("handling.treatedByHandling", 'search_treatedBy')
                            ->andWhere("(
                            handling.number LIKE :search_value
                            OR DATE_FORMAT(handling.creationDate, '%d/%m/%Y') LIKE :search_value
                            OR search_type.label LIKE :search_value
                            OR search_requester.username LIKE :search_value
                            OR handling.subject LIKE :search_value
                            OR DATE_FORMAT(handling.desiredDate, '%d/%m/%Y') LIKE :search_value
                            OR DATE_FORMAT(handling.validationDate, '%d/%m/%Y') LIKE :search_value
                            OR search_status.nom LIKE :search_value
                            OR search_treatedBy.username LIKE :search_value
                            OR handling.carriedOutOperationCount LIKE :search_value
						)")
                            ->setParameter('search_value', '%' . $search . '%');
                    }
                }
            }
        }

        if (!empty($params)) {
            if (!empty($params->all('order'))) {
                $order = $params->all('order')[0]['dir'];
                if (!empty($order)) {
                    $column = self::DtToDbLabels[$params->all('columns')[$params->all('order')[0]['column']]['data']];
                    if ($column === 'type') {
                        $qb = QueryBuilderHelper::joinTranslations($qb, $options['language'], $options['defaultLanguage'], ['type'], ["order" => $order]);
                    } else if ($column === 'requester') {
                        $qb
                            ->leftJoin('handling.requester', 'order_requester')
                            ->orderBy('order_requester.username', $order);
                    } else if ($column === 'status') {
                        $qb = QueryBuilderHelper::joinTranslations($qb, $options['language'], $options['defaultLanguage'], ['status'], ["order" => $order]);
                    } else if ($column === 'treatedBy') {
                        $qb
                            ->leftJoin('handling.treatedByHandling', 'order_treatedByHandling')
                            ->orderBy('order_treatedByHandling.username', $order);
                    } else {
                        if (property_exists(Handling::class, $column)) {
                            $qb
                                ->orderBy('handling.' . $column, $order);
                        }
                    }
                }
            }
        }

        // compte éléments filtrés
        $countFiltered = QueryBuilderHelper::count($qb, 'handling');

        if ($params->getInt('start')) $qb->setFirstResult($params->getInt('start'));
        if ($params->getInt('length')) $qb->setMaxResults($params->getInt('length'));

		$query = $qb
            ->select('handling')
            ->getQuery();

        return [
        	'data' => $query ? $query->getResult() : null,
            'count' => $countFiltered,
            'total' => $countTotal
        ];
    }

    public function findRequestToTreatByUserAndTypes(?Utilisateur $requester, int $limit, array $types = []) {
        $qb = $this->createQueryBuilder("h");

        if($requester) {
            $qb->andWhere("h.requester = :requester")
                ->setParameter("requester", $requester);
        }

        if(!empty($types)) {
            $qb
                ->andWhere("h.type IN (:types)")
                ->setParameter("types", $types);
        }

        return $qb->select("h")
            ->innerJoin("h.status", "s")
            ->leftJoin(AverageRequestTime::class, 'art', Join::WITH, 'art.type = h.type')
            ->andWhere("s.state IN (:handlingStatusesState)")
            ->setParameter('handlingStatusesState', [Statut::NOT_TREATED, Statut::IN_PROGRESS])
            ->addOrderBy('s.state', 'ASC')
            ->addOrderBy("DATE_ADD(h.creationDate, art.average, 'second')", 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function getProcessingTime() {
        $threeMonthsAgo = new DateTime("-3 month");

        return $this->createQueryBuilder("handling")
            ->select("handling_type.id AS type")
            ->addSelect("SUM(UNIX_TIMESTAMP(handling.validationDate) - UNIX_TIMESTAMP(handling.creationDate)) AS total")
            ->addSelect("COUNT(handling) AS count")
            ->join("handling.type", "handling_type")
            ->join("handling.status", "status")
            ->where("status.state = :treated")
            ->andWhere("handling.creationDate >= :from")
            ->andWhere("handling.validationDate IS NOT NULL")
            ->groupBy("handling.type")
            ->setParameter("from", $threeMonthsAgo)
            ->setParameter("treated", Statut::TREATED)
            ->getQuery()
            ->getArrayResult();
    }

    public function getLastNumberByDate(string $date): ?string {
        $result = $this->createQueryBuilder('handling')
            ->select('handling.number')
            ->where('handling.number LIKE :value')
            ->orderBy('handling.creationDate', 'DESC')
            ->addOrderBy('handling.number', 'DESC')
            ->addOrderBy('handling.id', 'DESC')
            ->setParameter('value', Handling::NUMBER_PREFIX . '-' . $date . '%')
            ->getQuery()
            ->execute();
        return $result ? $result[0]['number'] : null;
    }

    /**
     * @param DateTime $dateMin
     * @param DateTime $dateMax
     * @param array $options
     * @return int
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    public function countByDates(DateTime $dateMin,
                                 DateTime $dateMax,
                                 array $options)
    {

        $groupByTypes = $options['groupByTypes'] ?? false;
        $isOperations = $options['isOperations'] ?? false;
        $emergency = $options['emergency'] ?? false;
        $date = $options['date'] ?? 'desiredDate';
        $handlingStatusesFilter = $options['handlingStatusesFilter'] ?? [];
        $handlingTypesFilter = $options['handlingTypesFilter'] ?? [];

        $qb = $this->createQueryBuilder('handling')
            ->select(($isOperations ? 'SUM(handling.carriedOutOperationCount) AS count' : ('COUNT(handling) ' . ($groupByTypes ? ' AS count' : ''))))
            ->where("handling.$date BETWEEN :dateMin AND :dateMax")
            ->join('handling.type', 'type')
            ->setParameters([
                'dateMin' => $dateMin,
                'dateMax' => $dateMax
            ]);

        if ($groupByTypes) {
            $qb
                ->groupBy('type.id')
                ->addSelect('type.label as typeLabel');
        }

        if ($emergency) {
            $qb
                ->andWhere("handling.emergency NOT LIKE ''");
        }

        if (!empty($handlingStatusesFilter)) {
            $qb
                ->andWhere('handling.status IN (:handlingStatuses)')
                ->setParameter('handlingStatuses', $handlingStatusesFilter);
        }

        if (!empty($handlingTypesFilter)) {
            $qb
                ->andWhere('handling.type IN (:handlingTypes)')
                ->setParameter('handlingTypes', $handlingTypesFilter);
        }

        return $groupByTypes ? $qb->getQuery()->getResult() : $qb->getQuery()->getSingleScalarResult();
    }

    public function getOlderDateToTreat(array $types = [],
                                        array $statuses = []): ?DateTime {
        if (!empty($types) && !empty($statuses)) {
            $res = $this
                ->createQueryBuilder('handling')
                ->select('handling.creationDate AS date')
                ->innerJoin('handling.status', 'status')
                ->innerJoin('handling.type', 'type')
                ->andWhere('status IN (:statuses)')
                ->andWhere('type IN (:types)')
                ->andWhere('status.state IN (:treatedStates)')
                ->addOrderBy('handling.creationDate', 'ASC')
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

    public function getReceiversByDates(DateTime $dateMin,
                                        DateTime $dateMax) {
        $dateMin = $dateMin->format('Y-m-d H:i:s');
        $dateMax = $dateMax->format('Y-m-d H:i:s');

        $queryBuilder = $this->createQueryBuilder('handling')
            ->select('handling.id AS id')
            ->addSelect('join_receiver.username AS username')
            ->join('handling.receivers', 'join_receiver')
            ->where('handling.creationDate BETWEEN :dateMin AND :dateMax')
            ->setParameters([
                'dateMin' => $dateMin,
                'dateMax' => $dateMax
            ]);

        $res = $queryBuilder
            ->getQuery()
            ->getResult();

        return Stream::from($res)
            ->reduce(function (array $carry, array $handling) {
                $id = $handling['id'];
                $username = $handling['username'];

                if (!isset($carry[$id])) {
                    $carry[$id] = [];
                }

                $carry[$id][] = $username;

                return $carry;
            }, []);
    }

    public function countByDatesAndScale(int $scale, array $handlingTypes){
        $queryBuilder = $this->createQueryBuilder('handling');
    }
}
