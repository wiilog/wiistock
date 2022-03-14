<?php

namespace App\Repository;

use App\Entity\TransportRequestHistory;
use Doctrine\ORM\EntityRepository;

/**
 * @method TransportRequestHistory|null find($id, $lockMode = null, $lockVersion = null)
 * @method TransportRequestHistory|null findOneBy(array $criteria, array $orderBy = null)
 * @method TransportRequestHistory[]    findAll()
 * @method TransportRequestHistory[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TransportRequestHistoryRepository extends EntityRepository {}
