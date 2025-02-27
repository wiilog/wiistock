<?php

namespace App\Repository\Tracking;

use App\Entity\Tracking\TrackingDelay;
use App\Entity\Tracking\TrackingDelayRecord;
use App\Helper\QueryBuilderHelper;
use Doctrine\Common\Collections\Order;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query\Expr\Join;
use Symfony\Component\HttpFoundation\InputBag;

class TrackingDelayRecordRepository extends EntityRepository {

    /**
     * @return array{
     *     data: iterable<TrackingDelayRecord>,
     *     total: int,
     */
    public function iterateByTrackingDelay(TrackingDelay $trackingDelay,
                                           InputBag      $params): iterable {
        $queryBuilder = $this->createQueryBuilder('record')
            ->join('record.trackingDelay', 'trackingDelay', Join::WITH,  'record.trackingDelay = :trackingDelay')
            ->orderBy('record.date', Order::Descending->value)
            ->setParameter('trackingDelay',  $trackingDelay);

        $total = QueryBuilderHelper::count($queryBuilder, "record");

        if ($params->getInt('start')) {
            $queryBuilder->setFirstResult($params->getInt('start'));
        }

        if ($params->getInt('length')) {
            $queryBuilder->setMaxResults($params->getInt('length'));
        }

        return [
            "data" => $queryBuilder
                ->getQuery()
                ->toIterable(),
            "total" => $total,
        ];
    }
}
