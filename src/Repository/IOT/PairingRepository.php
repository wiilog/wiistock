<?php

namespace App\Repository\IOT;

use App\Entity\IOT\Pairing;
use App\Helper\QueryCounter;
use App\Entity\IOT\Sensor;
use DateTime;
use DateTimeZone;
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
class PairingRepository extends EntityRepository
{
    public function findByParams($params, Sensor $sensor)
    {

        $qb = $this->createQueryBuilder("sensors_pairing")
            ->leftJoin('sensors_pairing.sensorWrapper', 'sensor_wrapper')
            ->leftJoin('sensor_wrapper.sensor', 'sensor')
            ->where('sensor = :sensor')
            ->andWhere("sensors_pairing.end IS NULL OR sensors_pairing.end > :now")
            ->setParameter('sensor', $sensor)
            ->setParameter("now", new DateTime("now", new DateTimeZone('Europe/Paris')));

        $total = QueryCounter::count($qb, "sensors_pairing");

        if (!empty($params)) {
            if (!empty($params->get('search'))) {
                $search = $params->get('search')['value'];
                if (!empty($search)) {
                    $exprBuilder = $qb->expr();
                    $qb
                        ->andWhere('(' .
                            $exprBuilder->orX(
                                "DATE_FORMAT(sensors_pairing.start, '%d/%m/%Y') LIKE :value",
                                "DATE_FORMAT(sensors_pairing.end, '%d/%m/%Y') LIKE :value",
                                'search_article.barCode LIKE :value',
                                'search_collectOrder.numero LIKE :value',
                                'search_location.label LIKE :value',
                                'search_locationGroup.name LIKE :value',
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

            if (!empty($params->get('order'))) {
                $order = $params->get('order')[0]['dir'];
                if (!empty($order)) {
                    $column = $params->get('columns')[$params->get('order')[0]['column']]['data'];
                    switch ($column) {
                        case 'element':
                            $qb
                                ->orderBy('IFNULL(order_article.barCode,
                                    IFNULL(order_collectOrder.numero,
                                    IFNULL(order_location.label,
                                    IFNULL(order_pack.code,
                                    IFNULL(order_preparationOrder.numero,
                                    IFNULL(order_locationGroup.name, order_deliveryRequest.numero))))))', $order)
                                ->leftJoin('sensors_pairing.article', 'order_article')
                                ->leftJoin('sensors_pairing.collectOrder', 'order_collectOrder')
                                ->leftJoin('sensors_pairing.location', 'order_location')
                                ->leftJoin('sensors_pairing.locationGroup', 'order_locationGroup')
                                ->leftJoin('sensors_pairing.pack', 'order_pack')
                                ->leftJoin('sensors_pairing.preparationOrder', 'order_preparationOrder')
                                ->leftJoin('order_preparationOrder.demande', 'order_deliveryRequest');
                            break;
                        default:
                            if (property_exists(Pairing::class, $column)) {
                                $qb->orderBy('sensors_pairing.' . $column, $order);
                            }
                            break;
                    }
                }
            }
        }

        $countFiltered = QueryCounter::count($qb, 'sensors_pairing');

        if ($params) {
            if (!empty($params->get('start'))) $qb->setFirstResult($params->get('start'));
            if (!empty($params->get('length'))) $qb->setMaxResults($params->get('length'));
        }

        return [
            'data' => $qb->getQuery()->getResult(),
            'count' => $countFiltered,
            'total' => $total
        ];
    }

    public function findByParamsAndFilters(InputBag $filters) {
        $queryBuilder = $this->createQueryBuilder("pairing");
        if (!empty($filters)) {
            if ($filters->has('search') && !empty($filters->get('search'))) {
                $search = $filters->get('search');
                if (!empty($search)) {
                    $queryBuilder
                        ->leftJoin('pairing.sensorWrapper', 'search_sensorWrapper')
                        ->leftJoin('pairing.article', 'search_article')
                        ->leftJoin('pairing.collectOrder', 'search_collectOrder')
                        ->leftJoin('pairing.location', 'search_location')
                        ->leftJoin('pairing.locationGroup', 'search_locationGroup')
                        ->leftJoin('pairing.pack', 'search_pack')
                        ->leftJoin('pairing.preparationOrder', 'search_preparationOrder')
                        ->leftJoin('search_preparationOrder.demande', 'search_deliveryRequest')
                        ->andWhere($queryBuilder->expr()->orX(
                            "search_sensorWrapper.name LIKE :value",
                            'search_article.barCode LIKE :value',
                            'search_collectOrder.numero LIKE :value',
                            'search_location.label LIKE :value',
                            'locationGroup.name LIKE :value',
                            'search_pack.code LIKE :value',
                            'search_preparationOrder.numero LIKE :value',
                            'search_deliveryRequest.numero LIKE :value',
                        ))
                        ->setParameter('value', '%' . $search . '%');
                }
            }

            if($filters->has('filter') && !empty($filters->get('filter'))) {
                $queryBuilder
                    ->leftJoin('pairing.sensorWrapper', 'filter_sensorWrapper')
                    ->andWhere('filter_sensorWrapper.id IN (:filter_wrappers)')
                    ->setParameter('filter_wrappers', $filters->get('filter'), Connection::PARAM_STR_ARRAY);
            }

            if($filters->has('types') && !empty($filters->get('types'))) {
                $types = Stream::from($filters->get('types'))
                    ->map(fn($type) => array_search($type, Sensor::SENSOR_ICONS))
                    ->toArray();

                $queryBuilder
                    ->leftJoin('pairing.sensorWrapper', 'type_sensorWrapper')
                    ->leftJoin('type_sensorWrapper.sensor', 'type_sensor')
                    ->leftJoin('type_sensor.type', 'type')
                    ->andWhere('type.label IN (:types)')
                    ->setParameter('types', $types, Connection::PARAM_STR_ARRAY);
            }

            if($filters->has('elements') && !empty($filters->get('elements'))) {
                $elements = $filters->get('elements');
                $expr = $queryBuilder->expr()->orX();

                if(Stream::from($elements)->indexOf(Sensor::LOCATION) !== false) {
                    $queryBuilder
                        ->leftJoin('pairing.location', 'element_location');

                    $expr->add('element_location IS NOT NULL');
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

                if(Stream::from($elements)->indexOf(Sensor::COLLECT) !== false) {
                    $queryBuilder
                        ->leftJoin('pairing.collectOrder', 'element_collectOrder');

                    $expr->add('element_collectOrder IS NOT NULL');
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
            'data' => $query ? $query->getResult() : null
        ];
    }
}
