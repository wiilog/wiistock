<?php

namespace App\Repository;

use App\Entity\Reserve;
use App\Helper\QueryBuilderHelper;
use Doctrine\ORM\EntityRepository;
use Symfony\Component\HttpFoundation\InputBag;

/**
 * @method Reserve|null find($id, $lockMode = null, $lockVersion = null)
 * @method Reserve|null findOneBy(array $criteria, array $orderBy = null)
 * @method Reserve[]    findAll()
 * @method Reserve[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ReserveRepository extends EntityRepository
{

    public function save(Reserve $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Reserve $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findByParamsAndFilters(InputBag $params, $reserveType){
        $qb = $this->createQueryBuilder("reserve")
            ->leftJoin('reserve.line', 'truck_arrival_line')
            ->leftJoin('truck_arrival_line.truckArrival', 'truck_arrival')
            ->andWhere('truck_arrival.id = :truckArrivalId')
            ->andWhere('reserve.type = :reserveType')
            ->orderBy("truck_arrival_line.number", "DESC")
            ->setParameter('truckArrivalId', $params->get('truckArrival'))
            ->setParameter('reserveType', $reserveType);

        $countTotal = QueryBuilderHelper::count($qb, "reserve");

        //Filter search
        if (!empty($params)) {
            if (!empty($params->all("order"))) {
                $order = $params->all("order")[0]["dir"];
                if (!empty($order)) {
                    $column = $params->all('columns')[$params->all('order')[0]['column']]['data'];
                    switch ($column) {
                        case "actions":
                            break;
                        case "reserveLineNumber":
                            $qb
                                ->orderBy("truck_arrival_line.number", $order);
                            break;
                        default:
                            if (property_exists(Reserve::class, $column)) {
                                $qb->orderBy('reserve.' . $column, $order);
                            }
                            break;
                    }
                }
            }
        }

        // compte éléments filtrés
        $countFiltered = QueryBuilderHelper::count($qb, "reserve");

        if ($params->getInt("start")) $qb->setFirstResult($params->getInt("start"));
        if ($params->getInt("length")) $qb->setMaxResults(100);

        $query = $qb->getQuery();

        return [
            "data" => $query ? $query->getResult() : null,
            "count" => $countFiltered,
            "total" => $countTotal
        ];
    }
}
