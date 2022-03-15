<?php

namespace App\Repository;

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

        $qb = $this->createQueryBuilder('vehicle');
        $total = QueryCounter::count($qb, 'vehicle');

        if (!empty($params)) {
            if (!empty($params->get('search'))) {
                $search = $params->get('search')['value'];
                if (!empty($search)) {
                    $exprBuilder = $qb->expr();
                    $qb
                        ->andWhere($exprBuilder->orX(
                            'vehicle.registration LIKE :value',
                            'search_deliverer.username LIKE :value',
                            'search_locations.label LIKE :value'
                        ))
                        ->leftJoin('vehicle.deliverer', 'search_deliverer')
                        ->leftJoin('vehicle.locations', 'search_locations')
                        ->setParameter('value', '%' . $search . '%');
                }
            }

            if (!empty($params->get('order'))) {
                $order = $params->get('order')[0]['dir'];
                if (!empty($order)) {
                    $column = $params->get('columns')[$params->get('order')[0]['column']]['data'];

                    switch ($column) {
                        case 'deliverer':
                            $qb
                                ->leftJoin('vehicle.deliverer', 'order_vehicle')
                                ->orderBy("order_vehicle.username", $order);
                            break;
                        case 'locations':
                            $qb
                                ->leftJoin('vehicle.locations', 'order_locations')
                                ->orderBy('order_locations.label', $order);
                            break;
                        default:
                            $qb->orderBy('vehicle.' . $column, $order);
                            break;
                    }
                }
            }
        }

        $countFiltered = QueryCounter::count($qb, 'vehicle');

        if ($params->getInt('start')) {
            $qb->setFirstResult($params->getInt('start'));
        }

        if ($params->getInt('length')) {
            $qb->setMaxResults($params->getInt('length'));
        }

        return [
            'data' => $qb->getQuery()->getResult(),
            'count' => $countFiltered,
            'total' => $total
        ];
    }
}
