<?php

namespace App\Repository\Transport;

use App\Entity\Transport\TransportRequestLine;
use Doctrine\ORM\EntityRepository;

/**
 * @method TransportRequestLine|null find($id, $lockMode = null, $lockVersion = null)
 * @method TransportRequestLine|null findOneBy(array $criteria, array $orderBy = null)
 * @method TransportRequestLine[]    findAll()
 * @method TransportRequestLine[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TransportRequestLineRepository extends EntityRepository {}
