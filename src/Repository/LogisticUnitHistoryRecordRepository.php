<?php

namespace App\Repository;

use App\Entity\OperationHistory\LogisticUnitHistoryRecord;
use Doctrine\ORM\EntityRepository;

/**
 * @method LogisticUnitHistoryRecord|null find($id, $lockMode = null, $lockVersion = null)
 * @method LogisticUnitHistoryRecord|null findOneBy(array $criteria, array $orderBy = null)
 * @method LogisticUnitHistoryRecord[]    findAll()
 * @method LogisticUnitHistoryRecord[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class LogisticUnitHistoryRecordRepository extends EntityRepository{}
