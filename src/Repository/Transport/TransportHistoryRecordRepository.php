<?php

namespace App\Repository\Transport;

use App\Entity\OperationHistory\TransportHistoryRecord;
use Doctrine\ORM\EntityRepository;

/**
 * @method TransportHistoryRecord|null find($id, $lockMode = null, $lockVersion = null)
 * @method TransportHistoryRecord|null findOneBy(array $criteria, array $orderBy = null)
 * @method TransportHistoryRecord[]    findAll()
 * @method TransportHistoryRecord[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TransportHistoryRecordRepository extends EntityRepository {}
