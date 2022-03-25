<?php

namespace App\Repository\Transport;

use App\Entity\StatusHistory;
use Doctrine\ORM\EntityRepository;

/**
 * @method StatusHistory|null find($id, $lockMode = null, $lockVersion = null)
 * @method StatusHistory|null findOneBy(array $criteria, array $orderBy = null)
 * @method StatusHistory[]    findAll()
 * @method StatusHistory[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class StatusHistoryRepository extends EntityRepository {}
