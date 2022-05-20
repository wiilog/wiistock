<?php

namespace App\Repository;

use App\Entity\Transport\TransportRoundStartingHour;
use Doctrine\ORM\EntityRepository;

/**
 * @method TransportRoundStartingHour|null find($id, $lockMode = null, $lockVersion = null)
 * @method TransportRoundStartingHour|null findOneBy(array $criteria, array $orderBy = null)
 * @method TransportRoundStartingHour[]    findAll()
 * @method TransportRoundStartingHour[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TransportRoundStartingHourRepository extends EntityRepository {
}
