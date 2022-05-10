<?php

namespace App\Repository\Transport;

use App\Entity\Dispatch;
use App\Entity\FiltreSup;
use App\Entity\FreeField;
use App\Entity\Transport\TransportCollectRequest;
use App\Entity\Transport\TransportDeliveryRequest;
use App\Entity\Transport\TransportRequest;
use App\Entity\Utilisateur;
use App\Helper\QueryCounter;
use App\Service\VisibleColumnService;
use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query\Expr\Join;
use Symfony\Component\HttpFoundation\InputBag;
use WiiCommon\Helper\Stream;

/**
 * @method TransportRequest|null find($id, $lockMode = null, $lockVersion = null)
 * @method TransportRequest|null findOneBy(array $criteria, array $orderBy = null)
 * @method TransportRequest[]    findAll()
 * @method TransportRequest[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TransportRequestRepository extends EntityRepository {

    public function findByParamAndFilters(InputBag $params, array $filters, array $customFilters = [], $fromSubcontract = false): array {
        $qb = $this->createQueryBuilder("transport_request")
            ->leftJoin(TransportDeliveryRequest::class, "delivery", Join::WITH, "transport_request.id = delivery.id")
            ->leftJoin(TransportCollectRequest::class, "collect", Join::WITH, "transport_request.id = collect.id")
            ->leftJoin("collect.delivery", "collect_delivery")
            ->andWhere("(collect IS NULL OR collect_delivery IS NULL)");

        $total = QueryCounter::count($qb, "transport_request");

        if($params->get("dateMin")) {
            $date = \DateTime::createFromFormat("d/m/Y", $params->get("dateMin"));
            $date = $date->format("Y-m-d");

            $qb->andWhere('delivery.expectedAt >= :datetimeMin OR collect.expectedAt >= :dateMin')
                ->setParameter('datetimeMin', "$date 00:00:00")
                ->setParameter('dateMin', $date);
        }

        if($params->get("dateMax")) {
            $date = \DateTime::createFromFormat("d/m/Y", $params->get("dateMax"));
            $date = $date->format("Y-m-d");

            $qb->andWhere('delivery.expectedAt <= :datetimeMax OR collect.expectedAt <= :dateMax')
                ->setParameter('datetimeMax', "$date 23:59:59")
                ->setParameter('dateMax', $date);
        }

        // filtres sup
        foreach (array_merge($filters, $customFilters) as $filter) {
            switch ($filter['field']) {
                case FiltreSup::FIELD_STATUT:
                    $value = Stream::explode(",", $filter['value'])
                        ->map(fn($line) => explode(":", $line))
                        ->toArray();

                    $qb
                        ->join('transport_request.status', 'filter_status')
                        ->andWhere('filter_status.nom IN (:filter_status_value)')
                        ->setParameter('filter_status_value', $value);
                    break;
                case FiltreSup::FIELD_CATEGORY:
                    $qb->join("transport_request.type", "filter_category_type")
                        ->join("filter_category_type.category", "filter_category")
                        ->andWhere("filter_category.label LIKE :filter_category_value")
                        ->setParameter("filter_category_value","%".$filter['value']."%");
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
                case "subcontracted":
                    if (isset($filter['value'])) {
                        $qb
                            ->join('transport_request.orders', 'filter_subcontract_order')
                            ->andWhere('filter_subcontract_order.subcontracted = :filter_subcontract_value')
                            ->setParameter('filter_subcontract_value', $filter['value']);
                    }
            }
        }

        // compte éléments filtrés
        $countFiltered = QueryCounter::count($qb, "transport_request");

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

    public function findAwaitingValidation() {
        return $this->createQueryBuilder("request")
            ->join("request.status", "status")
            ->where("status.nom = :awaiting_validation")
            ->setParameter("awaiting_validation", TransportRequest::STATUS_AWAITING_VALIDATION)
            ->getQuery()
            ->getResult();
    }

    public function getLastNumberByDate(string $date): ?string {
        $result = $this->createQueryBuilder('request')
            ->select('request.number')
            ->where('request.number LIKE :value')
            ->orderBy('request.createdAt', Criteria::DESC)
            ->addOrderBy('request.number', Criteria::DESC)
            ->setParameter('value', $date . '%')
            ->getQuery()
            ->execute();
        return $result ? $result[0]['number'] : null;
    }
}
