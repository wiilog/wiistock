<?php

namespace App\Repository;

use App\Entity\Emplacement;
use App\Entity\TruckArrivalLine;
use App\Helper\QueryBuilderHelper;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query\Expr\Join;
use Symfony\Component\HttpFoundation\InputBag;

/**
 * @method TruckArrivalLine|null find($id, $lockMode = null, $lockVersion = null)
 * @method TruckArrivalLine|null findOneBy(array $criteria, array $orderBy = null)
 * @method TruckArrivalLine[]    findAll()
 * @method TruckArrivalLine[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TruckArrivalLineRepository extends EntityRepository
{

    public function save(TruckArrivalLine $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(TruckArrivalLine $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);
    }

    public function findByParamsAndFilters(InputBag $params){
        $qb = $this->createQueryBuilder("truck_arrival_line")
            ->leftJoin('truck_arrival_line.truckArrival', 'truck_arrival')
            ->andWhere('truck_arrival.id = :truckArrivalId')
            ->orderBy("truck_arrival_line.number", "DESC")
            ->setParameter('truckArrivalId', $params->get('truckArrival'));

        $countTotal = QueryBuilderHelper::count($qb, "truck_arrival_line");

        //Filter search
        if (!empty($params)) {
            if (!empty($params->all("search"))) {
                $search = $params->all("search")["value"];
                if (!empty($search)) {
                    $exprBuilder = $qb->expr();

                    $qb
                        ->leftJoin("truck_arrival.operator", "truck_arrival_operator")
                        ->leftJoin("truck_arrival_line.arrivals", "line_arrival")
                        ->andWhere($exprBuilder->orX(
                            "truck_arrival_operator.username LIKE :value",
                            "truck_arrival_line.number LIKE :value",
                            "line_arrival.numeroArrivage LIKE :value",
                            "IF(line_arrival.id IS NOT NULL, 'Oui', 'Non') LIKE :value",
                        ))
                        ->setParameter("value", "%$search%");
                }
            }

            if (!empty($params->all("order"))) {
                $order = $params->all("order")[0]["dir"];
                if (!empty($order)) {
                    $column = $params->all('columns')[$params->all('order')[0]['column']]['data'];
                    switch ($column) {
                        case "actions":
                            break;
                        case "lineNumber":
                            $qb
                                ->orderBy("truck_arrival_line.number", $order);
                            break;
                        case "operator":
                            $qb
                                ->leftJoin("truck_arrival.operator", "truck_arrival_operator")
                                ->orderBy("truck_arrival_operator.username", $order);
                            break;
                        case "associatedToUL":
                            $qb
                                ->leftJoin("truck_arrival_line.arrivals", "line_arrival")
                                ->orderBy("IF(line_arrival.id IS NOT NULL, 1, 0)", $order);
                            break;
                        case "arrivalLinks":
                            $qb
                                ->leftJoin("truck_arrival_line.arrivals", "line_arrival")
                                ->orderBy("line_arrival.numeroArrivage", $order);
                            break;
                        default:
                            if (property_exists(TruckArrivalLine::class, $column)) {
                                $qb->orderBy('truck_arrival_line.' . $column, $order);
                            }
                            break;
                    }
                }
            }
        }

        // compte éléments filtrés
        $countFiltered = QueryBuilderHelper::count($qb, "truck_arrival_line");

        if ($params->getInt("start")) $qb->setFirstResult($params->getInt("start"));
        if ($params->getInt("length")) $qb->setMaxResults($params->getInt("length"));

        $query = $qb->getQuery();

        return [
            "data" => $query ? $query->getResult() : null,
            "count" => $countFiltered,
            "total" => $countTotal
        ];
    }

    public function iterateAll(): array {
        return $this->createQueryBuilder('truck_arrival_line')
            ->select('truck_arrival_line.number AS number')
            ->addSelect('truck_arrival_line.id AS id')
            ->addSelect('join_reserve_type.disableTrackingNumber AS disableTrackingNumber')
            ->leftJoin('truck_arrival_line.reserve', 'join_reserve')
            ->leftJoin('join_reserve.reserveType', 'join_reserve_type')
            ->getQuery()
            ->getArrayResult();
    }

    public function getForSelect(?string $term, $option = []): array {
        $qb = $this->createQueryBuilder('truck_arrival_line');

        $strictSearch = $option['strictSearch'] ?? false;
        $term = $strictSearch ? $term : "%$term%";

        $qb ->select("truck_arrival_line.id AS id")
            ->addSelect("truck_arrival_line.number AS text")
            ->addSelect("truck_arrival.number AS truck_arrival_number")
            ->addSelect("truck_arrival.id AS truck_arrival_id")
            ->addSelect("driver.id AS driver_id")
            ->addSelect("driver.prenom AS driver_first_name")
            ->addSelect("driver.nom AS driver_last_name")
            ->addSelect("MAX(arrivals.id) AS arrivals_id")
            ->andWhere("truck_arrival_line.number LIKE :term")
            ->andWhere($qb->expr()->orX(
                "reserveType.disableTrackingNumber IS NULL",
                "reserveType.disableTrackingNumber = 0"
            ))
            ->join('truck_arrival_line.truckArrival', 'truck_arrival')
            ->leftJoin('truck_arrival.driver', 'driver')
            ->leftJoin('truck_arrival_line.arrivals', 'arrivals')
            ->leftJoin('truck_arrival_line.reserve', 'reserve')
            ->leftJoin('reserve.reserveType', 'reserveType')
            ->setParameter('term', $term);


        if (isset($option['truckArrivalId'])) {
            $qb
                ->andWhere('truck_arrival.id = :truck_arrival_id')
                ->setParameter('truck_arrival_id', $option['truckArrivalId']);
        }

        if (isset($option['carrierId'])) {
            $qb
                ->andWhere('truck_arrival.carrier = :carrier_id')
                ->setParameter('carrier_id', $option['carrierId']);
        }

        if (strlen(str_replace('%', '', $term)) == 0) {
            $qb->andWhere('arrivals.id IS NULL');
        }

        $qb
            ->addGroupBy('truck_arrival_line.id')
            ->addGroupBy('truck_arrival_line.number')
            ->addGroupBy('truck_arrival.number')
            ->addGroupBy('truck_arrival.id')
            ->addGroupBy('driver.id')
            ->addGroupBy('driver.prenom')
            ->addGroupBy('driver.nom');

        return $qb
            ->getQuery()
            ->getArrayResult();
    }

    public function getForReserve(?int $truckArrivalId): array {
        $qb = $this->createQueryBuilder('truck_arrival_line')
            ->select("truck_arrival_line.id AS id")
            ->addSelect("truck_arrival_line.number AS number")
            ->andWhere("qualityReserve IS NULL")
            ->leftJoin("truck_arrival_line.reserve", 'qualityReserve')
            ->join('truck_arrival_line.truckArrival', 'truck_arrival', Join::WITH, "truck_arrival.id = :truckArrivalId")
            ->setParameter('truckArrivalId', "$truckArrivalId");

        return $qb->getQuery()->getArrayResult();
    }
}
