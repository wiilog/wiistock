<?php

namespace App\Repository\IOT;

use App\Entity\IOT\Sensor;
use App\Entity\IOT\SensorWrapper;
use App\Helper\QueryCounter;
use Doctrine\ORM\EntityRepository;

/**
 * @method SensorWrapper|null find($id, $lockMode = null, $lockVersion = null)
 * @method SensorWrapper|null findOneBy(array $criteria, array $orderBy = null)
 * @method SensorWrapper[]    findAll()
 * @method SensorWrapper[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class SensorWrapperRepository extends EntityRepository
{

    public function findByParams($params) {

        $qb = $this->createQueryBuilder("sensor_wrapper")
            ->andWhere('sensor_wrapper.deleted = false');
        $total = QueryCounter::count($qb, "sensor_wrapper");

        if (!empty($params)) {
            if (!empty($params->get('search'))) {
                $search = $params->get('search')['value'];
                if (!empty($search)) {
                    $exprBuilder = $qb->expr();
                    $qb
                        ->andWhere('(' .
                            $exprBuilder->orX(
                                'search_sensorProfile.name LIKE :value',
                                'search_sensor.type LIKE :value',
                                'search_manager.username LIKE :value',
                                'sensor_wrapper.name LIKE :value',
                                'search_sensor.code LIKE :value',
                                "DATE_FORMAT(search_lastMessage.date, '%d/%m/%Y') LIKE :value"
                            )
                            . ')')
                        ->leftJoin('sensor_wrapper.sensor', 'search_sensor')
                        ->leftJoin('search_sensor.profile', 'search_sensorProfile')
                        ->leftJoin('search_sensor.lastMessage', 'search_lastMessage')
                        ->leftJoin('sensor_wrapper.manager', 'search_manager')
                        ->setParameter('value', '%' . $search . '%');
                }
            }

            if (!empty($params->get('order'))) {
                $order = $params->get('order')[0]['dir'];
                if (!empty($order)) {
                    $column = $params->get('columns')[$params->get('order')[0]['column']]['data'];

                    switch ($column) {
                        case 'profile':
                            $qb
                                ->leftJoin('sensor_wrapper.sensor', 'order_sensor')
                                ->leftJoin('order_sensor.profile', 'order_sensorProfile')
                                ->orderBy('order_sensorProfile.name', $order);
                            break;
                        case 'code':
                            $qb
                                ->leftJoin('sensor_wrapper.sensor', 'order_sensor')
                                ->orderBy('order_sensor.code', $order);
                            break;
                        case 'battery':
                            $qb
                                ->leftJoin('sensor_wrapper.sensor', 'order_sensor')
                                ->orderBy('order_sensor.battery', $order);
                            break;
                        case 'lastLift':
                            $qb
                                ->leftJoin('sensor_wrapper.sensor', 'order_sensor')
                                ->leftJoin('order_sensor.lastMessage', 'order_lastMessage')
                                ->orderBy('order_lastMessage.date', $order);
                            break;
                        case 'manager':
                            $qb
                                ->leftJoin('sensor_wrapper.manager', 'order_sensorWrapperManager')
                                ->orderBy('order_sensorWrapperManager.username', $order);
                            break;
                        default:
                            if (property_exists(Sensor::class, $column)) {
                                $qb
                                    ->orderBy('sensor_wrapper.' . $column, $order);
                            }
                            break;
                    }
                }
            }
        }

        $countFiltered = QueryCounter::count($qb, 'sensor_wrapper');

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
}
