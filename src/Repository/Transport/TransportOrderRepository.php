<?php

namespace App\Repository\Transport;

use App\Entity\CategoryType;
use App\Entity\FiltreSup;
use App\Entity\Transport\TransportCollectRequest;
use App\Entity\Transport\TransportDeliveryRequest;
use App\Entity\Transport\TransportOrder;
use App\Helper\QueryBuilderHelper;
use DateTime;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query\Expr\Join;
use Symfony\Component\HttpFoundation\InputBag;
use WiiCommon\Helper\Stream;

/**
 * @method TransportOrder|null find($id, $lockMode = null, $lockVersion = null)
 * @method TransportOrder|null findOneBy(array $criteria, array $orderBy = null)
 * @method TransportOrder[]    findAll()
 * @method TransportOrder[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TransportOrderRepository extends EntityRepository {

    public function findByParamAndFilters(InputBag $params, $filters, bool $subcontracts = false) {
        $qb = $this->createQueryBuilder("transport_order")
            ->join("transport_order.request", "transport_request")
            ->leftJoin(TransportDeliveryRequest::class, "delivery", Join::WITH, "transport_request.id = delivery.id")
            ->leftJoin(TransportCollectRequest::class, "collect", Join::WITH, "transport_request.id = collect.id")
            ->leftJoin("collect.delivery", "collect_delivery")
            ->leftJoin("transport_order.status", "status")
            ->andWhere("collect_delivery IS NULL")
            ->andWhere("transport_order.subcontracted = false")
            ->andWhere("status.code != :status_code")
            ->setParameter("status_code", TransportOrder::STATUS_AWAITING_VALIDATION);

        $total = QueryBuilderHelper::count($qb, "transport_order");

        if($params->get("dateMin")) {
            $date = \DateTime::createFromFormat("d/m/Y", $params->get("dateMin"));
            $date = $date->format("Y-m-d");

            $qb->andWhere('delivery.expectedAt >= :datetimeMin OR COALESCE(collect.validatedDate, collect.expectedAt) >= :dateMin')
                ->setParameter('datetimeMin', "$date 00:00:00")
                ->setParameter('dateMin', $date);
        }

        if($params->get("dateMax")) {
            $date = \DateTime::createFromFormat("d/m/Y", $params->get("dateMax"));
            $date = $date->format("Y-m-d");

            $qb->andWhere('delivery.expectedAt <= :datetimeMax OR COALESCE(collect.validatedDate, collect.expectedAt) <= :dateMax')
                ->setParameter('datetimeMax', "$date 23:59:59")
                ->setParameter('dateMax', $date);
        }

        foreach ($filters as $filter) {
            switch ($filter['field']) {
                case FiltreSup::FIELD_STATUT:
                    $value = Stream::explode(",", $filter['value'])
                        ->map(fn($line) => explode(":", $line))
                        ->toArray();

                    $qb
                        ->join('transport_order.status', 'filter_status')
                        ->andWhere('filter_status.nom IN (:status)')
                        ->setParameter('status', $value);
                    break;
                case FiltreSup::FIELD_CATEGORY:
                    $qb->join("transport_request.type", "filter_category_type")
                        ->join("filter_category_type.category", "filter_category")
                        ->andWhere("filter_category.label LIKE :filter_category_value")
                        ->setParameter("filter_category_value", $filter['value']);
                    break;
                case FiltreSup::FIELD_TYPE:
                    $qb
                        ->andWhere("transport_request.type = :filter_type_value")
                        ->setParameter("filter_type_value", $filter['value']);
                    break;
                case FiltreSup::FIELD_FILE_NUMBER:
                    $qb->join("transport_request.contact", "filter_contact_file_number")
                        ->andWhere("filter_contact_file_number.fileNumber LIKE :filter_file_number")
                        ->setParameter("filter_file_number", "%" . $filter['value'] . "%");
                    break;
                case FiltreSup::FIELD_CONTACT:
                    $qb->join("transport_request.contact", "filter_contact")
                        ->andWhere("filter_contact.name LIKE :filter_contact_name")
                        ->setParameter("filter_contact_name", "%" . $filter['value'] . "%");
                    break;
                case FiltreSup::FIELD_REQUESTERS:
                    $value = explode(',', $filter['value']);
                    $qb
                        ->join('transport_request.createdBy', 'filter_requester')
                        ->andWhere('filter_requester.id in (:filter_requester_values)')
                        ->setParameter('filter_requester_values', $value);
                    break;
            }
        }

        // compte éléments filtrés
        $countFiltered = QueryBuilderHelper::count($qb, "transport_order");

        if ($params->getInt('start')) {
            $qb->setFirstResult($params->getInt('start'));
        }
        if ($params->getInt('length')) {
            $qb->setMaxResults($params->getInt('length'));
        }

        $qb->orderBy("IFNULL(delivery.expectedAt, IFNULL(transport_request.validatedDate, collect.expectedAt))", "DESC");

        return [
            "data" => $qb->getQuery()->getResult(),
            "count" => $countFiltered,
            "total" => $total,
        ];
    }

    public function findOrdersForPlanning(\DateTime $start, \DateTime $end, array $statuses) {
        $statuses = !empty($statuses)
            ? $statuses
            : [TransportOrder::STATUS_TO_ASSIGN, TransportOrder::STATUS_ASSIGNED, TransportOrder::STATUS_ONGOING];

        $qb = $this->createQueryBuilder("transport_order")
            ->join("transport_order.status", "status")
            ->join("transport_order.request", "request")
            ->leftJoin(TransportDeliveryRequest::class, "delivery", Join::WITH, "request.id = delivery.id")
            ->leftJoin(TransportCollectRequest::class, "collect", Join::WITH, "request.id = collect.id")
            ->where("status.code IN (:planning_orders_statuses)")
            ->andWhere("IFNULL(DATE_FORMAT(request.validatedDate, '%Y-%m-%d'), IFNULL(DATE_FORMAT(delivery.expectedAt, '%Y-%m-%d'), collect.expectedAt)) BETWEEN :start AND :end")
            ->andWhere("transport_order.subcontracted = 0")
            ->setParameter("planning_orders_statuses", $statuses)
            ->setParameter("start", $start->format('Y-m-d'))
            ->setParameter("end", $end->format('Y-m-d'));

        return $qb->getQuery()->getResult();
    }

    // find by date
    public function findToAssignByDate(\DateTime $date)
    {
        return $this->createQueryBuilder("transport_order")
            ->join("transport_order.request", "request")
            ->leftJoin(TransportDeliveryRequest::class, "delivery", Join::WITH, "request.id = delivery.id")
            ->leftJoin(TransportCollectRequest::class, "collect", Join::WITH, "request.id = collect.id")
            ->where("IFNULL(DATE_FORMAT(request.validatedDate, '%Y-%m-%d'), IFNULL(DATE_FORMAT(delivery.expectedAt, '%Y-%m-%d'), collect.expectedAt)) = :date")
            ->join("transport_order.status", "status")
            ->andWhere("status.code in (:statuses)")
            ->setParameter("statuses", [TransportOrder::STATUS_TO_ASSIGN])
            ->setParameter("date", $date->format('Y-m-d'))
            ->getQuery()
            ->getResult();
    }

    public function iterateTransportOrderByDates(DateTime $dateMin, DateTime $dateMax, string $type): iterable {
        $dateMin = $dateMin->format("Y-m-d");
        $dateMax = $dateMax->format("Y-m-d");
        $qb = $this->createQueryBuilder('transport_order')
            ->join("transport_order.request", "transport_request")
            ->leftJoin(TransportDeliveryRequest::class, "delivery", Join::WITH, "transport_request.id = delivery.id")
            ->leftJoin(TransportCollectRequest::class, "collect", Join::WITH, "transport_request.id = collect.id")
            ->leftJoin("collect.delivery", "collect_delivery")
            ->setParameter('dateMin' , "$dateMin 00:00:00")
            ->setParameter('dateMax' , "$dateMax 23:59:59");

        if($type === CategoryType::DELIVERY_TRANSPORT) {
            $qb
                ->where('delivery.expectedAt BETWEEN :dateMin AND :dateMax')
                ->andWhere('delivery.id IS NOT NULL');
        } else {
            $qb
                ->where(' IFNULL(collect.validatedDate , collect.expectedAt) BETWEEN :dateMin AND :dateMax')
                ->andWhere("collect_delivery IS NULL");
        }
        return $qb
            ->getQuery()
            ->toIterable();
    }
}
