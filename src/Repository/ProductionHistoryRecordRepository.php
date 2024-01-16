<?php

namespace App\Repository;

use App\Entity\OperationHistory\ProductionHistoryRecord;
use Doctrine\ORM\EntityRepository;

/**
 * @method ProductionHistoryRecord|null find($id, $lockMode = null, $lockVersion = null)
 * @method ProductionHistoryRecord|null findOneBy(array $criteria, array $orderBy = null)
 * @method ProductionHistoryRecord[]    findAll()
 * @method ProductionHistoryRecord[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ProductionHistoryRecordRepository extends EntityRepository {}
