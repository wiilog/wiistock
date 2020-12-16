<?php

namespace App\Repository;

use App\Entity\Dispatch;
use App\Entity\FiltreSup;
use App\Entity\Statut;
use App\Entity\Utilisateur;
use App\Service\VisibleColumnService;
use DateTime;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;


/**
 * @method Dispatch|null find($id, $lockMode = null, $lockVersion = null)
 * @method Dispatch|null findOneBy(array $criteria, array $orderBy = null)
 * @method Dispatch[]    findAll()
 * @method Dispatch[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class DispatchRepository extends EntityRepository
{
    public function findByParamAndFilters($params, $filters) {
        $qb = $this->createQueryBuilder('dispatch');
        $exprBuilder = $qb->expr();

        $countTotal = $qb
            ->select('COUNT(dispatch.id)')
            ->getQuery()
            ->getSingleScalarResult();

        // filtres sup
        foreach ($filters as $filter) {
            switch ($filter['field']) {
                case 'statut':
                    $value = explode(',', $filter['value']);
					$qb
						->join('dispatch.statut', 'filter_status')
						->andWhere('filter_status.id in (:statut)')
						->setParameter('statut', $value);
					break;
                case FiltreSup::FIELD_MULTIPLE_TYPES:
                    $value = explode(',', $filter['value']);
                    $qb
                        ->join('dispatch.type', 'filter_type')
                        ->andWhere('filter_type.id in (:filter_type_value)')
                        ->setParameter('filter_type_value', $value);
                    break;
                case FiltreSup::FIELD_REQUESTERS:
                    $value = explode(',', $filter['value']);
                    $qb
                        ->join('dispatch.requester', 'filter_requester')
                        ->andWhere('filter_requester.id in (:filter_requester_values)')
                        ->setParameter('filter_requester_values', $value);
                    break;
                case FiltreSup::FIELD_RECEIVERS:
                    $value = explode(',', $filter['value']);
                    $qb
                        ->join('dispatch.receiver', 'filter_receiver')
                        ->andWhere('filter_receiver.id in (:filter_receiver_values)')
                        ->setParameter('filter_receiver_values', $value);
                    break;
                case FiltreSup::FIELD_CARRIERS:
                    $value = explode(',', $filter['value']);
                    $qb
                        ->join('dispatch.carrier', 'filter_carrier')
                        ->andWhere('filter_carrier.id in (:filter_carrier_value)')
                        ->setParameter('filter_carrier_value', $value);
                    break;
                case FiltreSup::FIELD_DISPATCH_NUMBER:
                    $value = explode(',', $filter['value']);
                    $qb->andWhere("dispatch.id = :filter_dispatchnumber_value")
                        ->setParameter("filter_dispatchnumber_value", $value);
                    break;
                case FiltreSup::FIELD_EMERGENCY_MULTIPLE:
                    $value = array_map(function($value) {
                        return explode(":", $value)[0];
                    }, explode(',', $filter['value']));

                    $qb->andWhere("dispatch.emergency IN (:filter_emergencies_value)")
                        ->setParameter("filter_emergencies_value", $value);
                    break;
                case 'dateMin':
                    $qb->andWhere('dispatch.creationDate >= :filter_dateMin_value')
                        ->setParameter('filter_dateMin_value', $filter['value'] . ' 00.00.00');
                    break;
                case 'dateMax':
                    $qb->andWhere('dispatch.creationDate <= :filter_dateMax_value')
                        ->setParameter('filter_dateMax_value', $filter['value'] . ' 23:59:59');
                    break;
            }
        }
        if (!empty($params)) {
            if (!empty($params->get('search'))) {
                $search = $params->get('search')['value'];
                if (!empty($search)) {
                    $qb
                        ->andWhere('(' . $exprBuilder->orX(
                            "DATE_FORMAT(dispatch.creationDate, '%e/%m/%Y') LIKE :search_value",
                            "DATE_FORMAT(dispatch.validationDate, '%e/%m/%Y') LIKE :search_value",
                            "DATE_FORMAT(dispatch.treatmentDate, '%e/%m/%Y') LIKE :search_value",
                            "DATE_FORMAT(dispatch.endDate, '%e/%m/%Y') LIKE :search_value",
                            'search_type.label LIKE :search_value',
                            'search_requester.username LIKE :search_value',
                            'search_receiver.username LIKE :search_value',
                            'dispatch.number LIKE :search_value',
                            'search_locationFrom.label LIKE :search_value',
                            'search_locationTo.label LIKE :search_value',
                            'search_statut.nom LIKE :search_value',
                            'dispatch.freeFields LIKE :search_value'
                        ) . ')')
                        ->leftJoin('dispatch.locationFrom', 'search_locationFrom')
                        ->leftJoin('dispatch.locationTo', 'search_locationTo')
                        ->leftJoin('dispatch.statut', 'search_statut')
                        ->leftJoin('dispatch.type', 'search_type')
                        ->leftJoin('dispatch.requester','search_requester')
                        ->leftJoin('dispatch.receiver', 'search_receiver')
                        ->setParameter('search_value', '%' . $search . '%');
                }
            }
            if (!empty($params->get('order'))) {
                $order = $params->get('order')[0]['dir'];
                if (!empty($order)) {
                    $column = $params->get('columns')[$params->get('order')[0]['column']]['data'];
                    if ($column === 'status') {
                        $qb
                            ->leftJoin('dispatch.statut', 'sort_status')
                            ->orderBy('sort_status.nom', $order);
                    } else if ($column === 'requester') {
                        $qb
                            ->leftJoin('dispatch.requester', 'sort_requester')
                            ->orderBy('sort_requester.username', $order);
                    } else if ($column === 'receiver') {
                        $qb
                            ->leftJoin('dispatch.receiver', 'sort_receiver')
                            ->orderBy('sort_receiver.username', $order);
                    } else if ($column === 'type') {
                        $qb
                            ->leftJoin('dispatch.type', 'sort_type')
                            ->orderBy('sort_type.label', $order);
                    } else if ($column === 'locationFrom') {
                        $qb
                            ->leftJoin('dispatch.locationFrom', 'sort_locationFrom')
                            ->orderBy('sort_locationFrom.label', $order);
                    } else if ($column === 'locationTo') {
                        $qb
                            ->leftJoin('dispatch.locationTo', 'sort_locationTo')
                            ->orderBy('sort_locationTo.label', $order);
                    } else {
                        $freeFieldId = VisibleColumnService::extractFreeFieldId($column);
                        if(is_numeric($freeFieldId)) {
                            $qb->orderBy("JSON_EXTRACT(dispatch.freeFields, '$.\"$freeFieldId\"')", $order);
                        } else if (property_exists(Dispatch::class, $column)) {
                            $qb->orderBy("dispatch.$column", $order);
                        }
                    }
                }
            }
        }

        // compte éléments filtrés
        $countFiltered = $qb
            ->getQuery()
            ->getSingleScalarResult();

        $qb->select('dispatch');

        if ($params) {
            if (!empty($params->get('start'))) $qb->setFirstResult($params->get('start'));
            if (!empty($params->get('length'))) $qb->setMaxResults($params->get('length'));
        }

        $query = $qb->getQuery();

        return [
            'data' => $query ? $query->getResult() : null,
            'count' => $countFiltered,
            'total' => $countTotal
        ];
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
            "SELECT COUNT(a)
            FROM App\Entity\Dispatch a
            WHERE a.receiver = :user OR a.requester = :user"
        )->setParameter('user', $user);

        return $query->getSingleScalarResult();
    }

    public function countByEmplacement($emplacementId)
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
        /** @lang DQL */
            "SELECT COUNT(a)
            FROM App\Entity\Dispatch a
            WHERE a.locationFrom = :emplacementId
            OR a.locationTo = :emplacementId"
        )->setParameter('emplacementId', $emplacementId);

        return $query->getSingleScalarResult();
    }

    /**
     * @param $date
     * @return mixed|null
     */
    public function getLastNumberByDate(string $date): ?string {
        $result = $this->createQueryBuilder('dispatch')
            ->select('dispatch.number')
            ->where('dispatch.number LIKE :value')
            ->orderBy('dispatch.creationDate', 'DESC')
            ->setParameter('value', Dispatch::PREFIX_NUMBER . '-' . $date . '%')
            ->getQuery()
            ->execute();
        return $result ? $result[0]['number'] : null;
    }

    public function getMobileDispatches(Utilisateur $user)
    {
        $queryBuilder = $this->createQueryBuilder('dispatch');
        $queryBuilder
            ->select('dispatch_requester.username AS requester')
            ->addSelect('dispatch.id AS id')
            ->addSelect('dispatch.number AS number')
            ->addSelect('dispatch.startDate AS startDate')
            ->addSelect('dispatch.endDate AS endDate')
            ->addSelect('dispatch.emergency AS emergency')
            ->addSelect('locationFrom.label AS locationFromLabel')
            ->addSelect('locationTo.label AS locationToLabel')
            ->addSelect('type.label AS typeLabel')
            ->addSelect('type.id AS typeId')
            ->addSelect('status.nom AS statusLabel')
            ->join('dispatch.requester', 'dispatch_requester')
            ->leftJoin('dispatch.locationFrom', 'locationFrom')
            ->leftJoin('dispatch.locationTo', 'locationTo')
            ->join('dispatch.type', 'type')
            ->join('dispatch.statut', 'status')
            ->where('status.needsMobileSync = 1')
            ->andWhere('status.state IN (:untreatedStates)')
            ->andWhere('type.id IN (:dispatchTypeIds)')
            ->setParameter('untreatedStates', [Statut::NOT_TREATED, Statut::PARTIAL])
            ->setParameter('dispatchTypeIds', $user->getDispatchTypeIds());

        return $queryBuilder
            ->getQuery()
            ->getResult();
    }

    /**
     * @param DateTime $dateMin
     * @param DateTime $dateMax
     * @return Dispatch[]
     */
    public function getByDates(DateTime $dateMin,
                               DateTime $dateMax)
    {
        $dateMax = $dateMax->format('Y-m-d H:i:s');
        $dateMin = $dateMin->format('Y-m-d H:i:s');

        $queryBuilder = $this->createQueryBuilder('dispatch')
            ->select('dispatch.id')
            ->addSelect('dispatch.number AS number')
            ->addSelect('dispatch.creationDate AS creationDate')
            ->addSelect('dispatch.validationDate AS validationDate')
            ->addSelect('dispatch.endDate AS endDate')
            ->addSelect('join_type.label AS type')
            ->addSelect('join_requester.username AS requester')
            ->addSelect('join_receiver.username AS receiver')
            ->addSelect('join_locationFrom.label AS locationFrom')
            ->addSelect('join_locationTo.label AS locationTo')
            ->addSelect('join_dispatchPack_pack.code AS packCode')
            ->addSelect('join_dispatchPack_nature.label AS packNatureLabel')
            ->addSelect('join_dispatchPack_pack.quantity AS packQuantity')
            ->addSelect('join_dispatchPack_pack.weight AS weight')
            ->addSelect('join_dispatchPack_lastTracking.datetime AS lastMovement')
            ->addSelect('join_dispatchPack_lastTracking_location.label AS lastLocation')
            ->addSelect('join_dispatchPack_lastTracking_operator.username AS operator')
            ->addSelect('join_status.nom AS status')
            ->addSelect('dispatch.emergency AS emergency')
            ->addSelect('join_treatedBy.username AS treatedBy')
            ->addSelect('dispatch.freeFields')

            ->leftJoin('dispatch.dispatchPacks', 'join_dispatchPack')
            ->leftJoin('join_dispatchPack.pack', 'join_dispatchPack_pack')
            ->leftJoin('join_dispatchPack_pack.nature', 'join_dispatchPack_nature')
            ->leftJoin('join_dispatchPack_pack.lastTracking', 'join_dispatchPack_lastTracking')
            ->leftJoin('join_dispatchPack_lastTracking.emplacement', 'join_dispatchPack_lastTracking_location')
            ->leftJoin('join_dispatchPack_lastTracking.operateur', 'join_dispatchPack_lastTracking_operator')

            ->leftJoin('dispatch.type', 'join_type')
            ->leftJoin('dispatch.requester', 'join_requester')
            ->leftJoin('dispatch.receiver', 'join_receiver')
            ->leftJoin('dispatch.treatedBy', 'join_treatedBy')
            ->leftJoin('dispatch.locationFrom', 'join_locationFrom')
            ->leftJoin('dispatch.locationTo', 'join_locationTo')
            ->leftJoin('dispatch.statut', 'join_status')

            ->andWhere('dispatch.creationDate BETWEEN :dateMin AND :dateMax')

            ->setParameters([
                'dateMin' => $dateMin,
                'dateMax' => $dateMax
            ]);

        return $queryBuilder
            ->getQuery()
            ->getResult();
    }

    public function getDispatchNumbers($search) {
        return $this->createQueryBuilder("dispatch")
            ->select("dispatch.id, dispatch.number AS text")
            ->where("dispatch.number LIKE :search")
            ->setParameter("search", "%$search%")
            ->getQuery()
            ->getResult();
    }

}
