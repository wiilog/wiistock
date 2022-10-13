<?php

namespace App\Repository\IOT;

use App\Entity\IOT\Pairing;
use App\Entity\IOT\Sensor;
use App\Entity\IOT\SensorWrapper;
use App\Helper\QueryBuilderHelper;
use DateTime;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityRepository;
use Symfony\Component\HttpFoundation\InputBag;
use WiiCommon\Helper\Stream;

/**
 * @method Pairing|null find($id, $lockMode = null, $lockVersion = null)
 * @method Pairing|null findOneBy(array $criteria, array $orderBy = null)
 * @method Pairing[]    findAll()
 * @method Pairing[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PairingRepository extends EntityRepository {

    public function findByParams(InputBag $params, SensorWrapper $wrapper) {

        $qb = $this->createQueryBuilder("sensors_pairing")
            ->leftJoin('sensors_pairing.sensorWrapper', 'sensor_wrapper')
            ->where('sensor_wrapper = :sensor_wrapper')
            ->andWhere("(sensors_pairing.end IS NULL OR sensors_pairing.end > :now)")
            ->setParameter('sensor_wrapper', $wrapper)
            ->setParameter("now", new DateTime("now"));

        $total = QueryBuilderHelper::count($qb, "sensors_pairing");

        if(!empty($params)) {
            if(!empty($params->all('search'))) {
                $search = $params->all('search')['value'];
                if(!empty($search)) {
                    $exprBuilder = $qb->expr();
                    $qb
                        ->andWhere('(' .
                            $exprBuilder->orX(
                                "DATE_FORMAT(sensors_pairing.start, '%d/%m/%Y') LIKE :value",
                                "DATE_FORMAT(sensors_pairing.end, '%d/%m/%Y') LIKE :value",
                                'search_article.barCode LIKE :value',
                                'search_collectOrder.numero LIKE :value',
                                'search_location.label LIKE :value',
                                'search_locationGroup.label LIKE :value',
                                'search_pack.code LIKE :value',
                                'search_preparationOrder.numero LIKE :value',
                                'search_deliveryRequest.numero LIKE :value',
                            )
                            . ')')
                        ->leftJoin('sensors_pairing.article', 'search_article')
                        ->leftJoin('sensors_pairing.collectOrder', 'search_collectOrder')
                        ->leftJoin('sensors_pairing.location', 'search_location')
                        ->leftJoin('sensors_pairing.locationGroup', 'search_locationGroup')
                        ->leftJoin('sensors_pairing.pack', 'search_pack')
                        ->leftJoin('sensors_pairing.preparationOrder', 'search_preparationOrder')
                        ->leftJoin('search_preparationOrder.demande', 'search_deliveryRequest')
                        ->setParameter('value', '%' . $search . '%');
                }
            }

            if(!empty($params->all('order'))) {
                $order = $params->all('order')[0]['dir'];
                if(!empty($order)) {
                    $column = $params->all('columns')[$params->all('order')[0]['column']]['data'];
                    switch($column) {
                        case 'element':
                            $qb
                                ->orderBy('IFNULL(order_article.barCode,
                                    IFNULL(order_collectOrder.numero,
                                    IFNULL(order_location.label,
                                    IFNULL(order_pack.code,
                                    IFNULL(order_preparationOrder.numero,
                                    IFNULL(order_locationGroup.label, order_deliveryRequest.numero))))))', $order)
                                ->leftJoin('sensors_pairing.article', 'order_article')
                                ->leftJoin('sensors_pairing.collectOrder', 'order_collectOrder')
                                ->leftJoin('sensors_pairing.location', 'order_location')
                                ->leftJoin('sensors_pairing.locationGroup', 'order_locationGroup')
                                ->leftJoin('sensors_pairing.pack', 'order_pack')
                                ->leftJoin('sensors_pairing.preparationOrder', 'order_preparationOrder')
                                ->leftJoin('order_preparationOrder.demande', 'order_deliveryRequest');
                            break;
                        default:
                            if(property_exists(Pairing::class, $column)) {
                                $qb->orderBy('sensors_pairing.' . $column, $order);
                            }
                            break;
                    }
                }
            }
        }

        $countFiltered = QueryBuilderHelper::count($qb, 'sensors_pairing');

        if($params->getInt('start')) $qb->setFirstResult($params->getInt('start'));
        if($params->getInt('length')) $qb->setMaxResults($params->getInt('length'));

        return [
            'data' => $qb->getQuery()->getResult(),
            'count' => $countFiltered,
            'total' => $total,
        ];
    }

    public function countAllActive() {
        return $this->createQueryBuilder("pairing")
            ->select('COUNT(pairing)')
            ->leftJoin('pairing.sensorWrapper', 'order_sensorWrapper')
            ->leftJoin('order_sensorWrapper.sensor', 'order_sensor')
            ->leftJoin('order_sensor.type', 'order_type')
            ->andWhere('pairing.active = 1')
            ->andWhere('order_type.label <> :actionType')
            ->addOrderBy('order_sensorWrapper.name', 'ASC')
            ->setParameter('actionType', Sensor::ACTION)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findExpiredActive() {
        return $this->createQueryBuilder("pairing")
            ->andWhere("pairing.active = 1")
            ->andWhere("pairing.end IS NOT NULL AND pairing.end < :now")
            ->setParameter("now", new DateTime())
            ->getQuery()
            ->getResult();
    }

    public function findByParamsAndFilters(InputBag $filters) {
        $queryBuilder = $this->createQueryBuilder("pairing");

        if(!empty($filters)) {
            if($filters->has('search') && !empty($filters->get('search'))) {
                $search = $filters->get('search');
                if(!empty($search)) {
                    $queryBuilder
                        ->leftJoin('pairing.sensorWrapper', 'search_sensorWrapper')
                        ->leftJoin('pairing.article', 'search_article')
                        ->leftJoin('pairing.collectOrder', 'search_collectOrder')
                        ->leftJoin('pairing.location', 'search_location')
                        ->leftJoin('pairing.locationGroup', 'search_locationGroup')
                        ->leftJoin('pairing.pack', 'search_pack')
                        ->leftJoin('pairing.vehicle', 'search_vehicle')
                        ->leftJoin('pairing.preparationOrder', 'search_preparationOrder')
                        ->leftJoin('search_preparationOrder.demande', 'search_deliveryRequest')
                        ->andWhere($queryBuilder->expr()->orX(
                            "search_sensorWrapper.name LIKE :value",
                            'search_article.barCode LIKE :value',
                            'search_collectOrder.numero LIKE :value',
                            'search_location.label LIKE :value',
                            'search_locationGroup.label LIKE :value',
                            'search_pack.code LIKE :value',
                            'search_vehicle.registrationNumber LIKE :value',
                            'search_preparationOrder.numero LIKE :value',
                            'search_deliveryRequest.numero LIKE :value',
                        ))
                        ->setParameter('value', '%' . $search . '%');
                }
            }

            if($filters->has('filter') && !empty($filters->all('filter'))) {
                $queryBuilder
                    ->leftJoin('pairing.sensorWrapper', 'filter_sensorWrapper')
                    ->andWhere('filter_sensorWrapper.id IN (:filter_wrappers)')
                    ->setParameter('filter_wrappers', $filters->all('filter'), Connection::PARAM_STR_ARRAY);
            }

            if($filters->has('types') && !empty($filters->all('types'))) {
                $types = Stream::from($filters->all('types'))
                    ->map(fn($type) => array_search($type, Sensor::SENSOR_ICONS))
                    ->toArray();

                $queryBuilder
                    ->leftJoin('pairing.sensorWrapper', 'type_sensorWrapper')
                    ->leftJoin('type_sensorWrapper.sensor', 'type_sensor')
                    ->leftJoin('type_sensor.type', 'type')
                    ->andWhere('type.label IN (:types)')
                    ->setParameter('types', $types, Connection::PARAM_STR_ARRAY);
            }


            if($filters->has('elements') && !empty($filters->all('elements'))) {
                $elements = $filters->all('elements');
                $expr = $queryBuilder->expr()->orX();

                if(Stream::from($elements)->indexOf(Sensor::LOCATION) !== false) {
                    $queryBuilder
                        ->leftJoin('pairing.location', 'element_location')
                        ->leftJoin('pairing.locationGroup', 'element_location_group');

                    $expr->add('element_location IS NOT NULL');
                    $expr->add('element_location_group IS NOT NULL');
                }

                if(Stream::from($elements)->indexOf(Sensor::LOCATION_GROUP) !== false) {
                    $queryBuilder
                        ->leftJoin('pairing.locationGroup', 'element_locationGroup');

                    $expr->add('element_locationGroup IS NOT NULL');
                }

                if(Stream::from($elements)->indexOf(Sensor::PACK) !== false) {
                    $queryBuilder
                        ->leftJoin('pairing.pack', 'element_pack');

                    $expr->add('element_pack IS NOT NULL');
                }

                if(Stream::from($elements)->indexOf(Sensor::ARTICLE) !== false) {
                    $queryBuilder
                        ->leftJoin('pairing.article', 'element_article');

                    $expr->add('element_article IS NOT NULL');
                }

                if(Stream::from($elements)->indexOf(Sensor::PREPARATION) !== false) {
                    $queryBuilder
                        ->leftJoin('pairing.preparationOrder', 'element_preparationOrder');

                    $expr->add('element_preparationOrder IS NOT NULL');
                }

                if(Stream::from($elements)->indexOf(Sensor::DELIVERY_REQUEST) !== false) {
                    $queryBuilder
                        ->leftJoin('pairing.preparationOrder', 'element_preparationOrder')
                        ->leftJoin('element_preparationOrder.demande', 'element_deliveryRequest');

                    $expr->add('element_deliveryRequest IS NOT NULL');
                }

                if(Stream::from($elements)->indexOf(Sensor::COLLECT_REQUEST) !== false) {
                    $queryBuilder
                        ->leftJoin('pairing.collectOrder', 'element_collectOrder');

                    $expr->add('element_collectOrder IS NOT NULL');
                }

                if(Stream::from($elements)->indexOf(Sensor::VEHICLE) !== false) {
                    $queryBuilder
                        ->leftJoin('pairing.vehicle', 'element_vehicle');

                    $expr->add('element_vehicle IS NOT NULL');
                }

                $queryBuilder->andWhere($expr);
            }
        }

        $queryBuilder
            ->leftJoin('pairing.sensorWrapper', 'order_sensorWrapper')
            ->leftJoin('order_sensorWrapper.sensor', 'order_sensor')
            ->leftJoin('order_sensor.type', 'order_type')
            ->andWhere('pairing.active = 1')
            ->andWhere('order_type.label <> :actionType')
            ->addOrderBy('order_sensorWrapper.name', 'ASC')
            ->setParameter('actionType', Sensor::ACTION);

        $query = $queryBuilder->getQuery();
        return [
            'data' => $query ? $query->getResult() : null,
            'total' => $this->countAllActive(),
        ];
    }

}
