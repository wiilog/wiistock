<?php

namespace App\Repository;

use App\Entity\Dispatch;
use App\Entity\FiltreSup;
use App\Entity\Utilisateur;
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
    public function findByParamAndFilters($params, $filters, $freeFieldLabelsToIds) {
        $qb = $this->createQueryBuilder('d');
        $exprBuilder = $qb->expr();

        $countTotal = $qb
            ->select('COUNT(d.id)')
            ->getQuery()
            ->getSingleScalarResult();

        // filtres sup
        foreach ($filters as $filter) {
            switch ($filter['field']) {
                case 'statut':
                    $value = explode(',', $filter['value']);
					$qb
						->join('d.statut', 's')
						->andWhere('s.id in (:statut)')
						->setParameter('statut', $value);
					break;
                case FiltreSup::FIELD_MULTIPLE_TYPES:
                    $value = explode(',', $filter['value']);
                    $qb
                        ->join('d.type', 't')
                        ->andWhere('t.id in (:type)')
                        ->setParameter('type', $value);
                    break;
                case FiltreSup::FIELD_REQUESTERS:
                    $value = explode(',', $filter['value']);
                    $qb
                        ->join('d.requester', 'filter_requester')
                        ->andWhere('filter_requester.id in (:filter_requester_values)')
                        ->setParameter('filter_requester_values', $value);
                    break;
                case FiltreSup::FIELD_RECEIVERS:
                    $value = explode(',', $filter['value']);
                    $qb
                        ->join('d.receiver', 'filter_receiver')
                        ->andWhere('filter_receiver.id in (:filter_receiver_values)')
                        ->setParameter('filter_receiver_values', $value);
                    break;
                case 'dateMin':
                    $qb->andWhere('d.creationDate >= :dateMin')
                        ->setParameter('dateMin', $filter['value'] . ' 00.00.00');
                    break;
                case 'dateMax':
                    $qb->andWhere('d.creationDate <= :dateMax')
                        ->setParameter('dateMax', $filter['value'] . ' 23:59:59');
                    break;
            }
        }
        if (!empty($params)) {
            if (!empty($params->get('search'))) {
                $search = $params->get('search')['value'];
                if (!empty($search)) {
                    $qb
                        ->andWhere('(' . $exprBuilder->orX(
                            'd.creationDate LIKE :value',
                            'd.number LIKE :value',
                            'search_locationFrom.label LIKE :value',
                            'search_locationTo.label LIKE :value',
                            'search_statut.nom LIKE :value',
                            'd.creationDate LIKE :value'
                        ) . ')')
                        ->leftJoin('d.locationFrom', 'search_locationFrom')
                        ->leftJoin('d.locationTo', 'search_locationTo')
                        ->leftJoin('d.statut', 'search_statut')
                        ->leftJoin('d.type', 'search_type')
                        ->setParameter('value', '%' . $search . '%');
                }
            }
            if (!empty($params->get('order')))
            {
                $order = $params->get('order')[0]['dir'];
                if (!empty($order))
                {
                    $column = $params->get('columns')[$params->get('order')[0]['column']]['data'];
                    if ($column === 'status') {
                        $qb
                            ->leftJoin('d.statut', 'sort_status')
                            ->orderBy('sort_status.nom', $order);
                    } else if ($column === 'requester') {
                        $qb
                            ->leftJoin('d.requester', 'sort_requester')
                            ->orderBy('sort_requester.username', $order);
                    } else if ($column === 'receiver') {
                        $qb
                            ->leftJoin('d.receiver', 'sort_receiver')
                            ->orderBy('sort_receiver.username', $order);
                    } else if ($column === 'type') {
                        $qb
                            ->leftJoin('d.type', 'sort_type')
                            ->orderBy('sort_type.label', $order);
                    } else if ($column === 'locationFrom') {
                        $qb
                            ->leftJoin('d.locationFrom', 'sort_locationFrom')
                            ->orderBy('sort_locationFrom.label', $order);
                    } else if ($column === 'locationTo') {
                        $qb
                            ->leftJoin('d.locationTo', 'sort_locationTo')
                            ->orderBy('sort_locationTo.label', $order);
                    } else {
                        if (property_exists(Dispatch::class, $column)) {
                            $qb->orderBy('d.' . $column, $order);
                        } else {
                            $clId = $freeFieldLabelsToIds[trim(mb_strtolower($column))] ?? null;
                            if ($clId) {
                                $jsonOrderQuery = "CAST(JSON_EXTRACT(d.freeFields, '$.\"${clId}\"') AS CHAR)";
                                $qb->orderBy($jsonOrderQuery, $order);
                            }
                        }
                    }
                }
            }
        }

        // compte éléments filtrés
        $countFiltered = $qb
            ->getQuery()
            ->getSingleScalarResult();

        $qb->select('d');

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

    public function getLastDispatchNumberByPrefix($prefix)
    {
        $queryBuilder = $this->createQueryBuilder('dispatch');
        $queryBuilder
            ->select('dispatch.number')
            ->where('dispatch.number LIKE :value')
            ->orderBy('dispatch.creationDate', 'DESC')
            ->setParameter('value', $prefix . '%');

        $result = $queryBuilder
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
            ->addSelect('(dispatch.emergency IS NOT NULL) AS urgent')
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
            ->andWhere('status.treated = 0')
            ->andWhere('type.id IN (:dispatchTypeIds)')
            ->setParameter('dispatchTypeIds', $user->getDispatchTypeIds());

        return $queryBuilder
            ->getQuery()
            ->getResult();
    }

    public function getLastDeliveryNumber(DateTime $from)
    {
        $year = $from->format('y');
        $month = $from->format('m');
        $day = $from->format('d');

        $queryBuilder = $this->createQueryBuilder('dispatch');
        $queryBuilder
            ->select('dispatch.deliveryNoteNumber AS deliveryNoteNumber')
            ->where('dispatch.deliveryNoteNumber = :dispatchDeliveryNoteNumberBegin')
            ->orderBy('dispatch.deliveryNoteNumber', 'DESC')
            ->setParameter('dispatchDeliveryNoteNumberBegin', "${year}${month}${day}");

        $res = $queryBuilder
            ->getQuery()
            ->getResult();

        return !empty($res)
            ? $res[0]['deliveryNoteNumber']
            : null;
    }
}
