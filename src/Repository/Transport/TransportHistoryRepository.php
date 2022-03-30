<?php

namespace App\Repository\Transport;

use App\Entity\Transport\TransportHistory;
use Doctrine\ORM\EntityRepository;

/**
 * @method TransportHistory|null find($id, $lockMode = null, $lockVersion = null)
 * @method TransportHistory|null findOneBy(array $criteria, array $orderBy = null)
 * @method TransportHistory[]    findAll()
 * @method TransportHistory[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TransportHistoryRepository extends EntityRepository {}
