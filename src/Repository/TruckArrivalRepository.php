<?php

namespace App\Repository;

use App\Entity\FieldsParam;
use App\Entity\TruckArrival;
use App\Entity\Utilisateur;
use App\Helper\QueryBuilderHelper;
use App\Service\VisibleColumnService;
use Doctrine\ORM\EntityRepository;
use Symfony\Component\HttpFoundation\InputBag;

/**
 * @method TruckArrival|null find($id, $lockMode = null, $lockVersion = null)
 * @method TruckArrival|null findOneBy(array $criteria, array $orderBy = null)
 * @method TruckArrival[]    findAll()
 * @method TruckArrival[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TruckArrivalRepository extends EntityRepository
{

    public function save(TruckArrival $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(TruckArrival $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findByParamsAndFilters(InputBag $params, $filters, Utilisateur $user, VisibleColumnService $visibleColumnService): array {
        $qb = $this->createQueryBuilder('truckArrival');
        $countTotal =  QueryBuilderHelper::count($qb, 'truckArrival');

        if (!empty($params)) {
            if (!empty($params->all('search'))) {
                $search = $params->all('search')['value'];
                if (!empty($search)) {
                    $conditions = [
                        "driver" => "search_driver.nom LIKE :search_value",
                        "unloadingLocation" => "search_unloadingLocation.label LIKE :search_value",
                        "registrationNumber" => "truckArrival.registrationNumber LIKE :search_value",
                        "creationDate" => "DATE_FORMAT(truckArrival.creationDate, '%d/%m/%Y') LIKE :search_value",
                        "carrier" => "search_carrier.label LIKE :search_value",
                        "operator" => "search_operator.username LIKE :search_value",
                        "number" => "truckArrival.number LIKE :search_value",
                        "trackingLinesNumber" => "order_trackingLines.number LIKE :search_value",
                    ];

                    $visibleColumnService->bindSearchableColumns($conditions, 'truckArrival', $qb, $user, $search);

                    $qb
                        ->leftJoin('truckArrival.driver', 'search_driver')
                        ->leftJoin('truckArrival.unloadingLocation', 'search_unloadingLocation')
                        ->leftJoin('truckArrival.carrier', 'search_carrier')
                        ->leftJoin('truckArrival.operator', 'search_operator')
                        ->leftJoin('truckArrival.trackingLines', 'order_trackingLines');
                }
            }

            if (!empty($params->all('order'))) {
                $order = $params->all('order')[0]['dir'];
                if (!empty($order)) {
                    $column = $params->all('columns')[$params->all('order')[0]['column']]['data'];
                    switch ($column) {
                        case 'driver':
                            $qb
                                ->orderBy('order_driver.nom', $order)
                                ->leftJoin('truckArrival.driver', 'order_driver');
                            break;
                        case 'unloadingLocation':
                            $qb
                                ->orderBy('order_unloadingLocation.label', $order)
                                ->leftJoin('truckArrival.unloadingLocation', 'order_unloadingLocation');
                            break;
                        case 'registrationNumber':
                            $qb->orderBy('truckArrival.registrationNumber', $order);
                            break;
                        case 'carrier':
                            $qb
                                ->orderBy('order_carrier.label', $order)
                                ->leftJoin('truckArrival.carrier', 'order_carrier');
                            break;
                        case 'operator':
                            $qb
                                ->orderBy('order_operator.username', $order)
                                ->leftJoin('truckArrival.operator', 'order_operator');
                            break;
                        case 'number':
                            $qb->orderBy('truckArrival.number', $order);
                            break;
                        case 'reserves':
                            $qb
                                ->orderBy('COUNT(order_reserve.id)', $order)
                                ->leftJoin('truckArrival.reserves', 'order_reserve')
                                ->groupBy('truckArrival.id');
                            break;
                        case 'creationDate':
                            $qb->orderBy('truckArrival.creationDate', $order);
                            break;
                    }
                }
            }
        }

        foreach ($filters as $filter) {
            switch ($filter['field']) {
                case 'dateMin':
                    $qb
                        ->andWhere('truckArrival.creationDate >= :filter_value_dateMin')
                        ->setParameter('filter_value_dateMin', $filter['value'] . " 00:00:00");
                    break;
                case 'dateMax':
                    $qb
                        ->andWhere('truckArrival.creationDate <= :filter_value_dateMax')
                        ->setParameter('filter_value_dateMax', $filter['value'] . " 23:59:59");
                    break;
                case 'registrationNumber':
                    $qb
                        ->andWhere('truckArrival.registrationNumber LIKE :filter_value_registrationNumber')
                        ->setParameter('filter_value_registrationNumber', '%' . $filter['value'] . '%');
                    break;
                case 'truckArrivalNumber':
                    $qb
                        ->andWhere('truckArrival.number LIKE :filter_value_truckArrivalNumber')
                        ->setParameter('filter_value_truckArrivalNumber', '%' . $filter['value'] . '%');
                    break;
                case 'carrierTrackingNumber':
                    $qb
                        ->andWhere('filter_trackingLines.number LIKE :filter_value_carrierTrackingNumber')
                        ->leftJoin('truckArrival.trackingLines', 'filter_trackingLines')
                        ->setParameter('filter_value_carrierTrackingNumber', '%' . $filter['value'] . '%');
                    break;
                case 'carriers':
                    $value = explode(',', $filter['value']);
                    $qb
                        ->andWhere('filter_carrier.id IN (:filter_value_filteredCarriers)')
                        ->leftJoin('truckArrival.carrier', 'filter_carrier')
                        ->setParameter('filter_value_filteredCarriers', $value);
                    break;
                case 'drivers':
                    $value = explode(',', $filter['value']);
                    $qb
                        ->andWhere('filter_driver.id IN (:filter_value_filteredDrivers)')
                        ->leftJoin('truckArrival.driver', 'filter_driver')
                        ->setParameter('filter_value_filteredDrivers', $value);
                    break;
                case 'carrierTrackingNumberNotAssigned':
                    if ($filter['value'] == '1') {
                        $qb
                            ->andWhere('filter_arrival_notAssigned IS NULL')
                            ->leftJoin('truckArrival.trackingLines', 'filter_trackingLines_notAssigned')
                            ->leftJoin('filter_trackingLines_notAssigned.arrivals', 'filter_arrival_notAssigned');
                    }
                    break;
            }
        }

        $countFiltered = QueryBuilderHelper::count($qb, 'truckArrival');
        $qb
            ->groupBy('truckArrival.id');
        if ($params->getInt('start')) $qb->setFirstResult($params->getInt('start'));
        if ($params->getInt('length')) $qb->setMaxResults($params->getInt('length'));

        $truckArrivals = $qb->getQuery()->getResult();

        return [
            'data' => $truckArrivals ,
            'count' => $countFiltered,
            'total' => $countTotal
        ];
    }

    public function getLastNumberByDate(string $date): ?string {
        $result = $this->createQueryBuilder('truckArrival')
            ->select('truckArrival.number')
            ->where('truckArrival.number LIKE :value')
            ->orderBy('truckArrival.creationDate', 'DESC')
            ->addOrderBy('truckArrival.number', 'DESC')
            ->setParameter('value', '%' . $date . '%')
            ->getQuery()
            ->execute();
        return $result ? $result[0]['number'] : null;
    }
}
