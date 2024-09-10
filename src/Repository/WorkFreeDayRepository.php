<?php

namespace App\Repository;

use App\Entity\WorkFreeDay;
use Doctrine\Common\Collections\Order;
use Doctrine\ORM\EntityRepository;
use WiiCommon\Helper\Stream;

/**
 * @method WorkFreeDay|null   find($id, $lockMode = null, $lockVersion = null)
 * @method WorkFreeDay|null   findOneBy(array $criteria, array $orderBy = null)
 * @method WorkFreeDay[]      findAll()
 * @method WorkFreeDay[]      findBy(array $criteria, array $orderBy = null, $limite = null, $offset = null)
 */
class WorkFreeDayRepository extends EntityRepository
{
    public function getWorkFreeDaysToDateTime($needsFormat = false): array {
        $workFreeDays = $this->createQueryBuilder("work_free_day")
            ->select("work_free_day.day")
            ->orderBy("work_free_day.day", Order::Descending)
            ->getQuery()
            ->getResult();

        return Stream::from($workFreeDays)
            ->map(static fn(array $workFreeDay) => $needsFormat
                ? $workFreeDay["day"]->format("Y-m-d")
                : $workFreeDay["day"]
            )
            ->toArray();
    }
}
