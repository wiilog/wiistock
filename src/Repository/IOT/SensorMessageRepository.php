<?php

namespace App\Repository\IOT;

use App\Entity\Emplacement;
use App\Entity\IOT\Sensor;
use App\Entity\IOT\SensorMessage;
use App\Entity\LocationGroup;
use App\Helper\QueryBuilderHelper;
use Doctrine\ORM\EntityRepository;
use http\Exception\RuntimeException;
use Symfony\Component\HttpFoundation\InputBag;
use WiiCommon\Helper\Stream;

/**
 * @method SensorMessage|null find($id, $lockMode = null, $lockVersion = null)
 * @method SensorMessage|null findOneBy(array $criteria, array $orderBy = null)
 * @method SensorMessage[]    findAll()
 * @method SensorMessage[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class SensorMessageRepository extends EntityRepository
{

    public function findByParamsAndFilters(InputBag $params, Sensor $sensor):array {

        $qb = $this->createQueryBuilder("sensor_message")
            ->leftJoin('sensor_message.sensor', 'sensor')
            ->where('sensor = :sensor')
            ->setParameter('sensor', $sensor);

        $total = QueryBuilderHelper::count($qb, "sensor_message");

        //Filter search
        if (!empty($params)) {
            if (!empty($params->all('search'))) {
                $search = $params->all('search')['value'];
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

            if (!empty($params->all('order'))) {
                $order = $params->all('order')[0]['dir'];
                if (!empty($order)) {
                    $column = $params->all('columns')[$params->all('order')[0]['column']]['data'];
                    if (property_exists(SensorMessage::class, $column)) {
                        $qb
                            ->orderBy('sensor_message.' . $column, $order);
                    }
                }
            }
        }

        // counts filtered items
        $countFiltered = QueryBuilderHelper::count($qb, 'sensor_message');

        if ($params->getInt('start')) $qb->setFirstResult($params->getInt('start'));
        if ($params->getInt('length')) $qb->setMaxResults($params->getInt('length'));

        return [
            'data' => $qb->getQuery()->getResult(),
            'count' => $countFiltered,
            'total' => $total
        ];
    }

    public function insertRaw(array $message, array $linked) {
        $sensor = $message['sensor'];
        $payload = $message['payload'];
        $date = $message['date'];
        $content = $message['content'];
        $event = $message['event'];
        $queryRaw = "INSERT INTO sensor_message (sensor_id, payload, date, content, event) VALUES ($sensor, '$payload', '$date', '$content', '$event')";

        $connection = $this
            ->getEntityManager()
            ->getConnection();
        $connection->executeQuery($queryRaw);
        $lastInsertedId = $connection->executeQuery('SELECT LAST_INSERT_ID()')->fetchOne();

        $queryRaw = "UPDATE sensor SET last_message_id = $lastInsertedId WHERE sensor.id = $sensor";
        $connection->executeQuery($queryRaw);

        foreach ($linked as $link) {
            $type = $link['type'];
            $entityColumn = $link['entityColumn'];
            $values = $link['values'];

            $values = Stream::from($values)
                ->map(fn($value) => '(' . $value . ', ' . $lastInsertedId . ')')
                ->join(', ');

            $queryRaw = "INSERT INTO $type ($entityColumn, sensor_message_id) VALUES $values";
            $connection->executeQuery($queryRaw);
        }
    }

    public function getLastSensorMessage(mixed $entity) {
        $query = $this->createQueryBuilder("sensor_message");

        if($entity instanceof Emplacement) {
            $query->leftJoin("sensor_message.pairings", "join_pairings")
                ->leftJoin("join_pairings.location", "join_location")
                ->andWhere("join_location = :location")
                ->setParameter("location", $entity);
        } else if($entity instanceof LocationGroup) {
            $query->leftJoin("sensor_message.pairings", "join_pairings")
                ->leftJoin("join_pairings.locationGroup", "join_location_group")
                ->andWhere("join_location_group = :location_group")
                ->setParameter("location_group", $entity);
        } else {
            throw new RuntimeException("Unsupported entity");
        }

        return $query->orderBy("sensor_message.date", "DESC")
            ->addOrderBy("sensor_message.id", "DESC")
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
