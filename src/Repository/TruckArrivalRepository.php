<?php

namespace App\Repository;

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
                    foreach ($user->getVisibleColumns()['truckArrival'] ?? [] as $column) {
                        $qb->setParameter('search', '%' . $search . '%');
                        switch ($column) {
                            case 'driver':
                                $qb
                                    ->orWhere('driverJ.nom LIKE :search')
                                    ->leftJoin('truckArrival.driver', 'driverJ');
                                break;
                            case 'unloadingLocation':
                                $qb
                                    ->orWhere('unloadingLocationJ.label LIKE :search')
                                    ->leftJoin('truckArrival.unloadingLocation', 'unloadingLocationJ');
                                break;
                            case 'registrationNumber':
                                $qb
                                    ->orWhere('truckArrival.registrationNumber LIKE :search');
                                break;
                            case 'carrier':
                                $qb
                                    ->orWhere('carrierJ.label LIKE :search')
                                    ->leftJoin('truckArrival.carrier', 'carrierJ');
                                break;
                            case 'operator':
                                $qb
                                    ->orWhere('operatorJ.username LIKE :search')
                                    ->leftJoin('truckArrival.operator', 'operatorJ');
                                break;
                            case 'number':
                                $qb
                                    ->orWhere('truckArrival.number LIKE :search');
                                break;
                            case 'trackingLinesNumber':
                                $qb
                                    ->orWhere('trackingLinesJ.number LIKE :search')
                                    ->leftJoin('truckArrival.trackingLines', 'trackingLinesJ');
                                break;
                        }
                    }
                }
            }

            if (!empty($params->all('order'))) {
                $order = $params->all('order')[0]['dir'];
                if (!empty($order)) {
                    $column = $params->all('columns')[$params->all('order')[0]['column']]['data'];
                    switch ($column) {
                        case 'driver':
                            $qb
                                ->orderBy('driverJ.nom', $order)
                                ->leftJoin('truckArrival.driver', 'driverJ');
                            break;
                        case 'unloadingLocation':
                            $qb
                                ->orderBy('unloadingLocationJ.label', $order)
                                ->leftJoin('truckArrival.unloadingLocation', 'unloadingLocationJ');
                            break;
                        case 'registrationNumber':
                            $qb->orderBy('truckArrival.registrationNumber', $order);
                            break;
                        case 'carrier':
                            $qb
                                ->orderBy('carrierJ.label', $order)
                                ->leftJoin('truckArrival.carrier', 'carrierJ');
                            break;
                        case 'operator':
                            $qb
                                ->orderBy('operatorJ.username', $order)
                                ->leftJoin('truckArrival.operator', 'operatorJ');
                            break;
                        case 'number':
                            $qb->orderBy('truckArrival.number', $order);
                            break;
                        case 'reserves':
                            $qb
                                ->orderBy('COUNT(reserveJ.id)', $order)
                                ->leftJoin('truckArrival.reserves', 'reserveJ')
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
                        ->andWhere('truckArrival.creationDate >= :dateMin')
                        ->setParameter('dateMin', $filter['value'] . " 00:00:00");
                    break;
                case 'dateMax':
                    $qb
                        ->andWhere('truckArrival.creationDate <= :dateMax')
                        ->setParameter('dateMax', $filter['value'] . " 23:59:59");
                    break;
                case 'registrationNumber':
                    $qb
                        ->andWhere('truckArrival.registrationNumber LIKE :registrationNumber')
                        ->setParameter('registrationNumber', '%' . $filter['value'] . '%');
                    break;
                case 'truckArrivalNumber':
                    $qb
                        ->andWhere('truckArrival.number LIKE :truckArrivalNumber')
                        ->setParameter('truckArrivalNumber', '%' . $filter['value'] . '%');
                    break;
                case 'carrierTrackingNumber':
                    $qb
                        ->andWhere('trackingLinesJ.number LIKE :carrierTrackingNumber')
                        ->leftJoin('truckArrival.trackingLines', 'trackingLinesJ')
                        ->setParameter('carrierTrackingNumber', '%' . $filter['value'] . '%');
                    break;
                case 'carriers':
                    $value = explode(',', $filter['value']);
                    $qb
                        ->andWhere('carrier.id IN (:filteredCarriers)')
                        ->leftJoin('truckArrival.carrier', 'carrier')
                        ->setParameter('filteredCarriers', $value);
                    break;
                case 'drivers':
                    $value = explode(',', $filter['value']);
                    $qb
                        ->andWhere('driver.id IN (:filteredDrivers)')
                        ->leftJoin('truckArrival.driver', 'driver')
                        ->setParameter('filteredDrivers', $value);
                    break;
                case 'carrierTrackingNumberNotAssigned':
                    if ($filter['value'] == '1') {
                        $qb
                            ->andWhere('trackingLinesArrival IS NULL')
                            ->leftJoin('truckArrival.trackingLines', 'trackingLinesJ')
                            ->leftJoin('trackingLinesJ.arrivals', 'trackingLinesArrival');
                    }
                    break;
            }
        }

        $countFiltered = QueryBuilderHelper::count($qb, 'truckArrival');

        $truckarrivals = $qb->getQuery()->getResult();
        return [
            'data' => $truckarrivals ,
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


//    /**
//     * @return TruckArrival[] Returns an array of TruckArrival objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('t')
//            ->andWhere('t.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('t.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?TruckArrival
//    {
//        return $this->createQueryBuilder('t')
//            ->andWhere('t.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
