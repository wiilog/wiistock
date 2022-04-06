<?php

namespace App\Repository\Transport;

use App\Entity\Transport\TransportCollectRequestLine;
use Doctrine\ORM\EntityRepository;

/**
 * @method TransportCollectRequestLine|null find($id, $lockMode = null, $lockVersion = null)
 * @method TransportCollectRequestLine|null findOneBy(array $criteria, array $orderBy = null)
 * @method TransportCollectRequestLine[]    findAll()
 * @method TransportCollectRequestLine[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TransportCollectRequestLineRepository extends EntityRepository {}
