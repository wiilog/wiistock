<?php

namespace App\Repository\Transport;

use App\Entity\Transport\Vehicle;
use App\Helper\QueryCounter;
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
        $total = QueryCounter::count($queryBuilder, 'vehicle');

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

        $countFiltered = QueryCounter::count($queryBuilder, 'vehicle');

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
}
