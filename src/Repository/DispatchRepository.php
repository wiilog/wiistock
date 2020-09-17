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
    public function findByParamAndFilters($params, $filters) {
        $qb = $this->createQueryBuilder('a');
        $exprBuilder = $qb->expr();

        $countTotal = $qb
            ->select('COUNT(a.id)')
            ->getQuery()
            ->getSingleScalarResult();

        // filtres sup
        foreach ($filters as $filter) {
            switch ($filter['field']) {
                case 'statut':
                    $value = explode(',', $filter['value']);
					$qb
						->join('a.statut', 's')
						->andWhere('s.id in (:statut)')
						->setParameter('statut', $value);
					break;
                case FiltreSup::FIELD_MULTIPLE_TYPES:
                    $value = explode(',', $filter['value']);
                    $qb
                        ->join('a.type', 't')
                        ->andWhere('t.id in (:type)')
                        ->setParameter('type', $value);
                    break;
                case FiltreSup::FIELD_REQUESTERS:
                    $value = explode(',', $filter['value']);
                    $qb
                        ->join('a.requester', 'filter_requester')
                        ->andWhere('filter_requester.id in (:filter_requester_values)')
                        ->setParameter('filter_requester_values', $value);
                    break;
                case FiltreSup::FIELD_RECEIVERS:
                    $value = explode(',', $filter['value']);
                    $qb
                        ->join('a.receiver', 'filter_receiver')
                        ->andWhere('filter_receiver.id in (:filter_receiver_values)')
                        ->setParameter('filter_receiver_values', $value);
                    break;
                case 'dateMin':
                    $qb->andWhere('a.creationDate >= :dateMin')
                        ->setParameter('dateMin', $filter['value'] . ' 00.00.00');
                    break;
                case 'dateMax':
                    $qb->andWhere('a.creationDate <= :dateMax')
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
                            'a.creationDate LIKE :value',
                            'a.number LIKE :value',
                            'search_locationFrom.label LIKE :value',
                            'search_locationTo.label LIKE :value',
                            'search_statut.nom LIKE :value',
                            'a.creationDate LIKE :value'
                        ) . ')')
                        ->leftJoin('a.locationFrom', 'search_locationFrom')
                        ->leftJoin('a.locationTo', 'search_locationTo')
                        ->leftJoin('a.statut', 'search_statut')
                        ->leftJoin('a.type', 'search_type')
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
                            ->leftJoin('a.statut', 'sort_status')
                            ->orderBy('sort_status.nom', $order);
                    } else if ($column === 'requester') {
                        $qb
                            ->leftJoin('a.requester', 'sort_requester')
                            ->orderBy('sort_requester.username', $order);
                    } else if ($column === 'receiver') {
                        $qb
                            ->leftJoin('a.receiver', 'sort_receiver')
                            ->orderBy('sort_receiver.username', $order);
                    } else if ($column === 'type') {
                        $qb
                            ->leftJoin('a.type', 'sort_type')
                            ->orderBy('sort_type.label', $order);
                    } else if ($column === 'locationFrom') {
                        $qb
                            ->leftJoin('a.locationFrom', 'sort_locationFrom')
                            ->orderBy('sort_locationFrom.label', $order);
                    } else if ($column === 'locationTo') {
                        $qb
                            ->leftJoin('a.locationTo', 'sort_locationTo')
                            ->orderBy('sort_locationTo.label', $order);
                    }
                    else {
                        $qb
                            ->orderBy('a.' . $column, $order);
                    }
                }
            }
        }

        // compte éléments filtrés
        $countFiltered = $qb
            ->getQuery()
            ->getSingleScalarResult();

        $qb
            ->select('a');

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
            ->addSelect('dispatch.urgent AS urgent')
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
            ->addSelect('join_type.label AS type')
            ->addSelect('join_requester.username AS requester')
            ->addSelect('join_receiver.username AS receiver')
            ->addSelect('join_locationFrom.label AS locationFrom')
            ->addSelect('join_locationTo.label AS locationTo')
            ->addSelect('join_dispatchPack_pack.code AS packCode')
            ->addSelect('join_dispatchPack_nature.label AS packNatureLabel')
            ->addSelect('join_dispatchPack_pack.quantity AS packQuantity')
            ->addSelect('join_dispatchPack_lastTracking.datetime AS lastMovement')
            ->addSelect('join_dispatchPack_lastTracking_location.label AS lastLocation')
            ->addSelect('join_dispatchPack_lastTracking_operator.username AS operator')
            ->addSelect('join_status.nom AS status')
            ->addSelect('dispatch.urgent AS urgent')
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
}
