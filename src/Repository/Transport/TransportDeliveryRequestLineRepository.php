<?php

namespace App\Repository\Transport;

use App\Entity\Transport\TransportDeliveryRequestLine;
use Doctrine\ORM\EntityRepository;

/**
 * @method TransportDeliveryRequestLine|null find($id, $lockMode = null, $lockVersion = null)
 * @method TransportDeliveryRequestLine|null findOneBy(array $criteria, array $orderBy = null)
 * @method TransportDeliveryRequestLine[]    findAll()
 * @method TransportDeliveryRequestLine[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TransportDeliveryRequestLineRepository extends EntityRepository {}
