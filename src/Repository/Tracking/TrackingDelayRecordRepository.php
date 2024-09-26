<?php

namespace App\Repository\Tracking;

use App\Entity\Pack;
use App\Helper\QueryBuilderHelper;
use Doctrine\Common\Collections\Order;
use Doctrine\ORM\EntityRepository;
use Symfony\Component\HttpFoundation\InputBag;

class TrackingDelayRecordRepository extends EntityRepository
{
    public function findByFiltersAndPack(InputBag $params, Pack $pack): array
    {
        $queryBuilder = $this->createQueryBuilder('tracking_delay_record')
            ->leftJoin("tracking_delay_record.pack", "join_pack", "WITH", "join_pack.id = :pack")
            ->orderBy('tracking_delay_record.movementDate', Order::Descending->value)
            ->addOrderBy('tracking_delay_record.id', Order::Descending->value)
            ->setParameter("pack", $pack->getId());
        $countTotal =  QueryBuilderHelper::count($queryBuilder, 'tracking_delay_record');


        $countFiltered =  QueryBuilderHelper::count($queryBuilder, 'tracking_delay_record');

        if ($params->getInt('start')) $queryBuilder->setFirstResult($params->getInt('start'));
        if ($params->getInt('length')) $queryBuilder->setMaxResults($params->getInt('length'));

        return [
            'data' => $queryBuilder->getQuery()->getResult(),
            'count' => $countFiltered,
            'total' => $countTotal
        ];
    }
}
