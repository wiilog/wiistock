<?php

namespace App\Repository;

use App\Entity\TransportCollectRequest;
use Doctrine\ORM\EntityRepository;

/**
 * @method TransportCollectRequest|null find($id, $lockMode = null, $lockVersion = null)
 * @method TransportCollectRequest|null findOneBy(array $criteria, array $orderBy = null)
 * @method TransportCollectRequest[]    findAll()
 * @method TransportCollectRequest[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TransportCollectRequestRepository extends EntityRepository {}
