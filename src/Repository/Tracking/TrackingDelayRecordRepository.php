<?php

namespace App\Repository\Tracking;

use App\Entity\Tracking\TrackingDelayRecord;
use Doctrine\ORM\EntityRepository;

/**
 * @method TrackingDelayRecord|null find($id, $lockMode = null, $lockVersion = null)
 * @method TrackingDelayRecord|null findOneBy(array $criteria, array $orderBy = null)
 * @method TrackingDelayRecord[]    findAll()
 * @method TrackingDelayRecord[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TrackingDelayRecordRepository extends EntityRepository
{

}
