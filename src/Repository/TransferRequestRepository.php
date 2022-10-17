<?php

namespace App\Repository;

use App\Entity\AverageRequestTime;
use App\Entity\Statut;
use App\Entity\TransferRequest;
use App\Entity\Utilisateur;
use App\Helper\QueryBuilderHelper;
use DateTime;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\Query\Expr\Join;
use Symfony\Component\HttpFoundation\InputBag;

/**
 * @method TransferRequest|null find($id, $lockMode = null, $lockVersion = null)
 * @method TransferRequest|null findOneBy(array $criteria, array $orderBy = null)
 * @method TransferRequest[]    findAll()
 * @method TransferRequest[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TransferRequestRepository extends EntityRepository {

    public function findByParamsAndFilters(InputBag $params, $filters) {
        $qb = $this->createQueryBuilder("transfer_request");
        $total = QueryBuilderHelper::count($qb, "transfer_request");

        // filtres sup
        foreach ($filters as $filter) {
            switch ($filter['field']) {
                case 'statut':
                    $value = explode(',', $filter['value']);
                    $qb
                        ->join('transfer_request.status', 'filter_status')
                        ->andWhere('filter_status.id in (:status)')
                        ->setParameter('status', $value);
                    break;
                case 'requesters':
                    $value = explode(',', $filter['value']);
                    $qb
                        ->join('transfer_request.requester', 'filter_requester')
                        ->andWhere("filter_requester.id in (:filter_value_requester)")
                        ->setParameter('filter_value_requester', $value);
                    break;
                case 'operators':
                    $value = explode(',', $filter['value']);
                    $qb
                        ->join('transfer_request.order', 'filter_order')
                        ->join('filter_order.operator', 'filter_orderOperator')
                        ->andWhere("filter_orderOperator.id in (:filter_value_operators)")
                        ->setParameter('filter_value_operators', $value);
                    break;
                case 'dateMin':
                    $qb->andWhere('transfer_request.creationDate >= :dateMin')
                        ->setParameter('dateMin', $filter['value'] . " 00:00:00");
                    break;
                case 'dateMax':
                    $qb->andWhere('transfer_request.creationDate <= :dateMax')
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
                        ->andWhere('(' .
                            $exprBuilder->orX(
                                'transfer_request.number LIKE :value',
                                'search_requester.username LIKE :value',
                                'search_origin.label LIKE :value',
                                'search_destination.label LIKE :value',
                                'search_status.nom LIKE :value'
                            )
                            . ')')
                        ->leftJoin('transfer_request.requester', 'search_requester')
                        ->leftJoin('transfer_request.origin', 'search_origin')
                        ->leftJoin('transfer_request.destination', 'search_destination')
                        ->leftJoin('transfer_request.status', 'search_status')
                        ->setParameter('value', '%' . $search . '%');
                }
            }

            if (!empty($params->all('order'))) {
                $order = $params->all('order')[0]['dir'];
                if (!empty($order)) {
                    $column = $params->all('columns')[$params->all('order')[0]['column']]['data'];

                    switch ($column) {
                        case 'number':
                            $qb
                                ->orderBy("transfer_request.number", $order);
                            break;
                        case 'status':
                            $qb
                                ->leftJoin('transfer_request.status', 'order_status')
                                ->orderBy('order_status.nom', $order);
                            break;
                        case 'origin':
                            $qb
                                ->leftJoin('transfer_request.origin', 'order_origin')
                                ->orderBy('order_origin.label', $order);
                            break;
                        case 'destination':
                            $qb
                                ->leftJoin('transfer_request.destination', 'order_destination')
                                ->orderBy('order_destination.label', $order);
                            break;
                        case 'requester':
                            $qb
                                ->leftJoin('transfer_request.requester', 'order_requester')
                                ->orderBy('order_requester.username', $order);
                            break;
                        case 'creationDate':
                            $qb
                                ->orderBy("transfer_request.creationDate", $order);
                            break;
                        case 'validationDate':
                            $qb
                                ->orderBy("transfer_request.validationDate", $order);
                            break;
                        default:
                            $qb
                                ->orderBy('transfer_request.' . $column, $order);
                            break;
                    }
                }
            }
        }

        // compte éléments filtrés
        $countFiltered = QueryBuilderHelper::count($qb, 'transfer_request');

        if ($params->getInt('start')) $qb->setFirstResult($params->getInt('start'));
        if ($params->getInt('length')) $qb->setMaxResults($params->getInt('length'));

        return [
            'data' => $qb->getQuery()->getResult(),
            'count' => $countFiltered,
            'total' => $total
        ];
    }

    public function getProcessingTime() {
        $threeMonthsAgo = new DateTime("-3 months");

        return $this->createQueryBuilder("transfer_request")
            ->select("transfer_type.id AS type")
            ->addSelect("SUM(UNIX_TIMESTAMP(transfer_order.transferDate) - UNIX_TIMESTAMP(transfer_request.validationDate)) AS total")
            ->addSelect("COUNT(transfer_request) AS count")
            ->join("transfer_request.type", "transfer_type")
            ->join("transfer_request.order", "transfer_order")
            ->where("transfer_request.creationDate >= :from")
            ->andWhere("transfer_order.transferDate IS NOT NULL")
            ->groupBy("transfer_request.type")
            ->setParameter("from", $threeMonthsAgo)
            ->getQuery()
            ->getArrayResult();
    }

    public function findByStatutLabelAndUser($statutLabel, $user) {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "SELECT t
            FROM App\Entity\TransferRequest t
            JOIN t.status s
            WHERE s.nom = :statutLabel AND t.requester = :user "
        )->setParameters([
            'statutLabel' => $statutLabel,
            'user' => $user,
        ]);
        return $query->execute();
    }

    public function getLastNumberByDate(string $date): ?string {
        $result = $this->createQueryBuilder('transfer_request')
            ->select('transfer_request.number')
            ->where('transfer_request.number LIKE :value')
            ->orderBy('transfer_request.creationDate', 'DESC')
            ->addOrderBy('transfer_request.number', 'DESC')
            ->setParameter('value', TransferRequest::NUMBER_PREFIX . '-' . $date . '%')
            ->getQuery()
            ->getResult();
        return $result ? $result[0]['number'] : null;
    }

    public function findByDates($dateMin, $dateMax) {
        $qb = $this->createQueryBuilder("transfer_request");

        $qb
            ->where("transfer_request.creationDate BETWEEN :dateMin AND :dateMax")
            ->setParameters([
                'dateMin' => $dateMin,
                'dateMax' => $dateMax
            ]);

        return $qb
            ->getQuery()
            ->getResult();
    }

    public function findRequestToTreatByUser(?Utilisateur $requester, int $limit) {
        $qb = $this->createQueryBuilder("transfer_request");

        if($requester) {
            $qb->andWhere("transfer_request.requester = :requester")
                ->setParameter("requester", $requester);
        }

        return $qb->select("transfer_request")
            ->innerJoin("transfer_request.status", "s")
            ->leftJoin(AverageRequestTime::class, 'art', Join::WITH, 'art.type = transfer_request.type')
            ->andWhere("s.nom IN (:statuses)")
            ->addOrderBy('s.state', 'ASC')
            ->addOrderBy("DATE_ADD(transfer_request.creationDate, art.average, 'second')", 'ASC')
            ->setMaxResults($limit)
            ->setParameter("statuses", [TransferRequest::DRAFT, TransferRequest::TO_TREAT])
            ->getQuery()
            ->getResult();
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
                ->createQueryBuilder('transfer_request')
                ->select('transfer_request.validationDate AS date')
                ->innerJoin('transfer_request.status', 'status')
                ->innerJoin('transfer_request.type', 'type')
                ->andWhere('status IN (:statuses)')
                ->andWhere('type IN (:types)')
                ->andWhere('status.state IN (:treatedStates)')
                ->addOrderBy('transfer_request.validationDate', 'ASC')
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

    public function countByLocation($location): int {
        return $this->createQueryBuilder("transfer_request")
            ->select("COUNT(transfer_request)")
            ->andWhere("transfer_request.origin = :location OR transfer_request.destination = :location")
            ->setParameter("location", $location)
            ->getQuery()
            ->getSingleScalarResult();
    }

}
