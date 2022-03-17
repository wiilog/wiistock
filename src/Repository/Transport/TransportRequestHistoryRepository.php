<?php

namespace App\Repository\Transport;

use App\Entity\Transport\TransportRequestHistory;
use Doctrine\ORM\EntityRepository;

/**
 * @method TransportRequestHistory|null find($id, $lockMode = null, $lockVersion = null)
 * @method TransportRequestHistory|null findOneBy(array $criteria, array $orderBy = null)
 * @method TransportRequestHistory[]    findAll()
 * @method TransportRequestHistory[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TransportRequestHistoryRepository extends EntityRepository {}
