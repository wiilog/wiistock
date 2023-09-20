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

}
