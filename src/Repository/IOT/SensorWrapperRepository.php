<?php

namespace App\Repository\IOT;

use App\Entity\IOT\Sensor;
use App\Entity\IOT\SensorWrapper;
use App\Helper\QueryBuilderHelper;
use Doctrine\ORM\EntityRepository;
use Symfony\Component\HttpFoundation\InputBag;

/**
 * @method SensorWrapper|null find($id, $lockMode = null, $lockVersion = null)
 * @method SensorWrapper|null findOneBy(array $criteria, array $orderBy = null)
 * @method SensorWrapper[]    findAll()
 * @method SensorWrapper[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class SensorWrapperRepository extends EntityRepository
{

    public function findByParams(InputBag $params) {

        $qb = $this->createQueryBuilder("sensor_wrapper")
            ->andWhere('sensor_wrapper.deleted = false');
        $total = QueryBuilderHelper::count($qb, "sensor_wrapper");

        if (!empty($params)) {
            if (!empty($params->all('search'))) {
                $search = $params->all('search')['value'];
                if (!empty($search)) {
                    $exprBuilder = $qb->expr();
                    $qb
                        ->andWhere('(' .
                            $exprBuilder->orX(
                                'search_sensorProfile.name LIKE :value',
                                'search_type.label LIKE :value',
                                'search_manager.username LIKE :value',
                                'sensor_wrapper.name LIKE :value',
                                'search_sensor.code LIKE :value',
                                "DATE_FORMAT(search_lastMessage.date, '%d/%m/%Y') LIKE :value"
                            )
                            . ')')
                        ->leftJoin('sensor_wrapper.sensor', 'search_sensor')
                        ->leftJoin('search_sensor.type', 'search_type')
                        ->leftJoin('search_sensor.profile', 'search_sensorProfile')
                        ->leftJoin('search_sensor.lastMessage', 'search_lastMessage')
                        ->leftJoin('sensor_wrapper.manager', 'search_manager')
                        ->setParameter('value', '%' . $search . '%');
                }
            }

            if (!empty($params->all('order'))) {
                $order = $params->all('order')[0]['dir'];
                if (!empty($order)) {
                    $column = $params->all('columns')[$params->all('order')[0]['column']]['data'];

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
                        case 'type':
                            $qb
                                ->leftJoin('sensor_wrapper.sensor', 'order_sensor')
                                ->leftJoin('order_sensor.type', 'order_type')
                                ->orderBy('order_type.label', $order);
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

        $countFiltered = QueryBuilderHelper::count($qb, 'sensor_wrapper');

        if ($params->getInt('start')) $qb->setFirstResult($params->getInt('start'));
        if ($params->getInt('length')) $qb->setMaxResults($params->getInt('length'));

        return [
            'data' => $qb->getQuery()->getResult(),
            'count' => $countFiltered,
            'total' => $total
        ];
    }

    public function findByNameOrCode($sensorWrapper, $sensor)
    {
        $qb = $this->createQueryBuilder('sensor_wrapper');

        if ($sensorWrapper) {
            $qb
                ->andWhere('sensor_wrapper.id = :sensorWrapper')
                ->setParameter('sensorWrapper', $sensorWrapper);
        } else if ($sensor) {
            $qb
                ->join('sensor_wrapper.sensor', 'sensor')
                ->andWhere('sensor.id = :sensor')
                ->setParameter('sensor', $sensor);
        }

        return $qb
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findWithNoActiveAssociation($action = true) {
        $qb = $this->createQueryBuilder('sensor_wrapper')
            ->leftJoin('sensor_wrapper.pairings', 'pairing')
            ->where('sensor_wrapper.deleted = 0');

        if (!$action) {
            $qb
                ->leftJoin('sensor_wrapper.sensor', 'sensor')
                ->leftJoin('sensor.type', 'type')
                ->andWhere('type.label <> :actionType')
                ->setParameter('actionType', Sensor::ACTION);
        }
        return $qb
            ->getQuery()
            ->getResult();
    }
    public function getWithNoAssociationForSelect($term, $field, $onlyTrigger = false) {
        $queryBuilder = $this->createQueryBuilder('sensor_wrapper');
        $queryBuilder
            ->select('sensor_wrapper')
            ->leftJoin('sensor_wrapper.sensor', 'sensor');
        if($field == 'name'){
            $queryBuilder
                ->where('sensor_wrapper.name LIKE :term');
        }else{
            $queryBuilder
                ->where('sensor.code LIKE :term');
        }
        if($onlyTrigger){
             $queryBuilder
                ->leftJoin('sensor.type', 'type')
                ->andWhere('type.label != \''.Sensor::GPS."'");
        } else {
            $queryBuilder
                ->leftJoin('sensor.type', 'type')
                ->andWhere('type.label <> :actionType')
                ->setParameter('actionType', Sensor::ACTION);
        }
        return $queryBuilder
                    ->andWhere('sensor_wrapper.deleted = 0')
                    ->setParameter('term', "%$term%")
                    ->groupBy('sensor_wrapper.id')
                    ->getQuery()
                    ->getResult();
    }

    public function getForSelect(?string $term, $forPairing = false) {
        $qb = $this->createQueryBuilder("sensor_wrapper")
            ->select("sensor_wrapper.id AS id")
            ->addSelect("sensor_wrapper.name AS text")
            ->where("sensor_wrapper.name LIKE :term")
            ->andWhere("sensor_wrapper.deleted = 0")
            ->setParameter("term", "%$term%");

        if ($forPairing) {
            $qb
                ->leftJoin('sensor_wrapper.sensor', 'sensor')
                ->leftJoin('sensor.type', 'type')
                ->andWhere('type.label <> :actionType')
                ->setParameter('actionType', Sensor::ACTION);
        }

        return $qb
            ->getQuery()
            ->getResult();
    }
}
