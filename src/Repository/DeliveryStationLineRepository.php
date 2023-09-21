<?php

namespace App\Repository;

use App\Entity\DeliveryStationLine;
use Doctrine\ORM\EntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method DeliveryStationLine|null find($id, $lockMode = null, $lockVersion = null)
 * @method DeliveryStationLine|null findOneBy(array $criteria, array $orderBy = null)
 * @method DeliveryStationLine[]    findAll()
 * @method DeliveryStationLine[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class DeliveryStationLineRepository extends EntityRepository
{
    public function countByUser($user) {
        $qb = $this->createQueryBuilder('delivery_station_line');
        return $qb
            ->select("COUNT(delivery_station_line)")
            ->andWhere(':user MEMBER OF delivery_station_line.receivers')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
