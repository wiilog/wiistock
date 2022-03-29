<?php

namespace App\Repository\Transport;

use App\Entity\FiltreSup;
use App\Entity\Transport\TransportOrder;
use App\Helper\QueryCounter;
use Doctrine\ORM\EntityRepository;
use Symfony\Component\HttpFoundation\InputBag;

/**
 * @method TransportOrder|null find($id, $lockMode = null, $lockVersion = null)
 * @method TransportOrder|null findOneBy(array $criteria, array $orderBy = null)
 * @method TransportOrder[]    findAll()
 * @method TransportOrder[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TransportOrderRepository extends EntityRepository {

    public function findByParamAndFilters(InputBag $params, $filters) {
        $qb = $this->createQueryBuilder("transport_order")
            ->join("transport_order.request", "transport_request")
            ->andWhere("transport_order.subcontracted = false");

        $total = QueryCounter::count($qb, "transport_order");

        foreach ($filters as $filter) {
            switch ($filter['field']) {
                case FiltreSup::FIELD_DATE_MIN:
                    $qb->andWhere('transport_request.expectedAt >= :dateMin')
                        ->setParameter('dateMin', $filter['value'] . ' 00:00:00');
                    break;
                case FiltreSup::FIELD_DATE_MAX:
                    $qb->andWhere('transport_request.expectedAt <= :dateMax')
                        ->setParameter('dateMax', $filter['value'] . ' 23:59:59');
                    break;
                case FiltreSup::FIELD_STATUT:
                    $value = explode(',', $filter['value']);
                    $qb
                        ->join('transport_order.status', 'filter_status')
                        ->andWhere('filter_status.id IN (:status)')
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
                        ->andWhere("filter_contact.name = :filter_contact_name")
                        ->setParameter("filter_contact_name", "%" . $filter['value'] . "%");
                    break;
            }
        }

        // compte éléments filtrés
        $countFiltered = QueryCounter::count($qb, "transport_order");

        if ($params->getInt('start')) {
            $qb->setFirstResult($params->getInt('start'));
        }
        if ($params->getInt('length')) {
            $qb->setMaxResults($params->getInt('length'));
        }

        $qb->orderBy("transport_request.expectedAt", "ASC");

        return [
            "data" => $qb->getQuery()->getResult(),
            "count" => $countFiltered,
            "total" => $total,
        ];
    }

}
