<?php

namespace App\Repository\Transport;

use App\Entity\Transport\TransportRequest;
use Doctrine\ORM\EntityRepository;

/**
 * @method TransportRequest|null find($id, $lockMode = null, $lockVersion = null)
 * @method TransportRequest|null findOneBy(array $criteria, array $orderBy = null)
 * @method TransportRequest[]    findAll()
 * @method TransportRequest[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TransportRequestRepository extends EntityRepository {}
