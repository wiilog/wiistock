<?php


namespace App\Repository;


use DateTime;
use Doctrine\ORM\EntityRepository;

class LocationClusterMeterRepository extends EntityRepository {
    public function countByDate(DateTime $date,
                                string $locationClusterCodeInto,
                                string $locationClusterCodeFrom = null): int {
        $queryBuilder = $this->createQueryBuilder('meter');

        $queryBuilder
            ->select('COUNT(meter.dropCounter) AS counter')
            ->join('meter.locationClusterInto', 'locationClusterInto')
            ->leftJoin('meter.locationClusterFrom', 'locationClusterFrom')
            ->where('meter.date = :date')
            ->andWhere('locationClusterFrom.code = :fromCode')
            ->andWhere('locationClusterInto.code = :intoCode')
            ->setParameter('date', $date->format('Y-m-d'))
            ->setParameter('fromCode', $locationClusterCodeFrom)
            ->setParameter('intoCode', $locationClusterCodeInto);

        $res = $queryBuilder
            ->getQuery()
            ->getResult();

        return !empty($res)
            ? ($res[0]['counter'] ?? 0)
            : 0;
    }
}
