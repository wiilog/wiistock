<?php

namespace App\Repository;

use App\Entity\DisputeHistoryRecord;
use Doctrine\ORM\EntityRepository;

/**
 * @method DisputeHistoryRecord|null find($id, $lockMode = null, $lockVersion = null)
 * @method DisputeHistoryRecord|null findOneBy(array $criteria, array $orderBy = null)
 * @method DisputeHistoryRecord[]    findAll()
 * @method DisputeHistoryRecord[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class DisputeHistoryRecordRepository extends EntityRepository
{
}
