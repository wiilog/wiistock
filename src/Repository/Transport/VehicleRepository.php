<?php

namespace App\Repository\Transport;

use App\Entity\Transport\TransportRound;
use App\Entity\Transport\Vehicle;
use App\Helper\QueryBuilderHelper;
use DateTime;
use Doctrine\ORM\EntityRepository;
use Symfony\Component\HttpFoundation\InputBag;

/**
 * @method Vehicle|null find($id, $lockMode = null, $lockVersion = null)
 * @method Vehicle|null findOneBy(array $criteria, array $orderBy = null)
 * @method Vehicle[]    findAll()
 * @method Vehicle[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class VehicleRepository extends EntityRepository
{

    public function findByParams(InputBag $params): array
    {

        $queryBuilder = $this->createQueryBuilder('vehicle');
        $total = QueryBuilderHelper::count($queryBuilder, 'vehicle');

        if (!empty($params)) {
            if (!empty($params->all('search'))) {
                $search = $params->all('search')['value'];
                if (!empty($search)) {
                    $exprBuilder = $queryBuilder->expr();
                    $queryBuilder
                        ->andWhere($exprBuilder->orX(
                            'vehicle.registrationNumber LIKE :value',
                            'search_deliverer.username LIKE :value',
                            'search_locations.label LIKE :value'
                        ))
                        ->leftJoin('vehicle.deliverer', 'search_deliverer')
                        ->leftJoin('vehicle.locations', 'search_locations')
                        ->setParameter('value', '%' . $search . '%');
                }
            }

            if (!empty($params->all('order'))) {
                $order = $params->all('order')[0]['dir'];
                if (!empty($order)) {
                    $column = $params->all('columns')[$params->all('order')[0]['column']]['data'];

                    switch ($column) {
                        case 'deliverer':
                            $queryBuilder
                                ->leftJoin('vehicle.deliverer', 'order_vehicle')
                                ->orderBy("order_vehicle.username", $order);
                            break;
                        default:
                            if (property_exists(Vehicle::class, $column)) {
                                $queryBuilder->orderBy('vehicle.' . $column, $order);
                            }
                            break;
                    }
                }
            }
        }

        $countFiltered = QueryBuilderHelper::count($queryBuilder, 'vehicle');

        if ($params->getInt('start')) {
            $queryBuilder->setFirstResult($params->getInt('start'));
        }

        if ($params->getInt('length')) {
            $queryBuilder->setMaxResults($params->getInt('length'));
        }

        return [
            'data' => $queryBuilder->getQuery()->getResult(),
            'count' => $countFiltered,
            'total' => $total
        ];
    }

    public function getForSelect(?string $term) {
        return $this->createQueryBuilder("vehicle")
            ->select("vehicle.id AS id, vehicle.registrationNumber AS text")
            ->where("vehicle.registrationNumber LIKE :term")
            ->setParameter("term", "%$term%")
            ->getQuery()
            ->getArrayResult();
    }

    public function countRound(Vehicle $vehicle): int {
        return $this->getEntityManager()
            ->createQueryBuilder()
            ->from(TransportRound::class, 'round')
            ->select('COUNT(round)')
            ->andWhere('round.vehicle = :vehicle')
            ->setParameter('vehicle', $vehicle)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return TransportRound[]
     */
    public function findOngoingRounds(Vehicle $vehicle): array {
        $queryBuilder = $this->getEntityManager()->createQueryBuilder();
        $exprBuilder = $queryBuilder->expr();
        return $queryBuilder
            ->from(TransportRound::class, 'round')
            ->select('round')
            ->andWhere('round.vehicle = :vehicle')
            ->andWhere('round.beganAt < :now')
            ->andWhere($exprBuilder->orX(
                'round.endedAt IS NULL',
                'round.endedAt > :now'
            ))
            ->setParameter('vehicle', $vehicle)
            ->setParameter('now', new DateTime())
            ->getQuery()
            ->getResult();
    }

    public function findOneByDateLastMessageBetween(Vehicle $vehicle, DateTime $start, ?DateTime $end, string $type): ?array
    {
        return $this->createQueryBuilder("vehicle")
            ->select("sensor_message.content")
            ->join("vehicle.sensorMessages", "sensor_message")
            ->join('sensor_message.sensor' , 'sensor')
            ->join('sensor.type', 'type')
            ->where('sensor_message.date BETWEEN :start AND :end')
            ->andWhere('vehicle.id = :vehicle')
            ->andWhere('type.label = :type')
            ->orderBy('sensor_message.date', 'DESC')
            ->setMaxResults(1)
            ->setParameter('vehicle', $vehicle)
            ->setParameter('type', $type)
            ->setParameter('start', $start)
            ->setParameter('end', $end ?? new DateTime("now"))
            ->getQuery()
            ->getOneOrNullResult();
    }
}
