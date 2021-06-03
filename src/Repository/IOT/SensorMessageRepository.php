<?php

namespace App\Repository\IOT;

use App\Entity\IOT\Sensor;
use App\Entity\IOT\SensorMessage;
use App\Helper\QueryCounter;
use Doctrine\ORM\EntityRepository;

/**
 * @method SensorMessage|null find($id, $lockMode = null, $lockVersion = null)
 * @method SensorMessage|null findOneBy(array $criteria, array $orderBy = null)
 * @method SensorMessage[]    findAll()
 * @method SensorMessage[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class SensorMessageRepository extends EntityRepository
{

    public function findByParamsAndFilters($params, Sensor $sensor):array {

        $qb = $this->createQueryBuilder("sensor_message")
            ->leftJoin('sensor_message.sensor', 'sensor')
            ->where('sensor = :sensor')
            ->setParameter('sensor', $sensor);

        $total = QueryCounter::count($qb, "sensor_message");

        //Filter search
        if (!empty($params)) {
            if (!empty($params->get('search'))) {
                $search = $params->get('search')['value'];
                if (!empty($search)) {
                    $exprBuilder = $qb->expr();
                    $qb
                        ->andWhere('(' .
                            $exprBuilder->orX(
                                "DATE_FORMAT(sensor_message.date, '%d/%m/%Y') LIKE :value",
                                "sensor_message.event LIKE :value"
                            )
                            . ')')
                        ->setParameter('value', '%' . $search . '%');
                }
            }

            if (!empty($params->get('order'))) {
                $order = $params->get('order')[0]['dir'];
                if (!empty($order)) {
                    $column = $params->get('columns')[$params->get('order')[0]['column']]['data'];
                    if (property_exists(SensorMessage::class, $column)) {
                        $qb
                            ->orderBy('sensor_message.' . $column, $order);
                    }
                }
            }
        }

        // counts filtered items
        $countFiltered = QueryCounter::count($qb, 'sensor_message');

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
