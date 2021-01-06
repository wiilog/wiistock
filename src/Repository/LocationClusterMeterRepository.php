<?php


namespace App\Repository;


use App\Entity\LocationCluster;
use DateTime;
use Doctrine\ORM\EntityRepository;

class LocationClusterMeterRepository extends EntityRepository {
    public function countByDate(DateTime $date,
                                LocationCluster $locationClusterCodeInto,
                                ?LocationCluster $locationClusterCodeFrom = null): int {
        $queryBuilder = $this->createQueryBuilder('meter');

        $queryBuilder
            ->select('SUM(meter.dropCounter) AS counter')
            ->join('meter.locationClusterInto', 'locationClusterInto')
            ->leftJoin('meter.locationClusterFrom', 'locationClusterFrom')
            ->where('meter.date = :date')
            ->andWhere('locationClusterInto = :into')
            ->setParameter('date', $date->format('Y-m-d'))
            ->setParameter('into', $locationClusterCodeInto);

        if (!empty($locationClusterCodeFrom)) {
            $queryBuilder
                ->andWhere('locationClusterFrom = :from')
                ->setParameter('from', $locationClusterCodeFrom);
        }
        else {
            $queryBuilder
                ->andWhere('locationClusterFrom IS NULL');
        }

        $res = $queryBuilder
            ->getQuery()
            ->getResult();

        return !empty($res)
            ? ($res[0]['counter'] ?? 0)
            : 0;
    }
}
