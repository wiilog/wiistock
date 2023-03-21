<?php

namespace App\Repository;

use App\Entity\TruckArrivalLine;
use App\Helper\QueryBuilderHelper;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\EntityRepository;
use Doctrine\Persistence\ManagerRegistry;
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

    public function iterateAll(){
        return $this->createQueryBuilder('truck_arrival_line')
            ->select('truck_arrival_line.number')
            ->getQuery()
            ->getArrayResult();
    }

    public function getForSelect(?string $term, $option = []): array {
        $qb = $this
            ->createQueryBuilder('truck_arrival_line')
            ->select("truck_arrival_line.id AS id")
            ->addSelect("truck_arrival_line.number AS text")
            ->addSelect("truck_arrival.number AS truck_arrival_number")
            ->addSelect("truck_arrival.id AS truck_arrival_id")
            ->addSelect("driver.id AS driver_id")
            ->addSelect("driver.prenom AS driver_first_name")
            ->addSelect("driver.nom AS driver_last_name")
            ->andWhere("truck_arrival_line.number LIKE :term")
            ->join('truck_arrival_line.truckArrival', 'truck_arrival')
            ->join('truck_arrival.driver', 'driver')
            ->setParameter('term', "%$term%");


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

        return $qb->getQuery()->getArrayResult();
    }

    public function getForReserve(?int $truckArrivalId): array {
        $qb = $this
            ->createQueryBuilder('truck_arrival_line')
            ->select("truck_arrival_line.id AS id")
            ->addSelect("truck_arrival_line.number AS number")
            ->andWhere("truck_arrival.id = :truckArrivalId")
            ->andWhere("qualityReserve IS NULL")
            ->leftJoin("truck_arrival_line.reserve", 'qualityReserve')
            ->leftJoin('truck_arrival_line.truckArrival', 'truck_arrival')
            ->setParameter('truckArrivalId', "$truckArrivalId");

        return $qb->getQuery()->getArrayResult();
    }
}
