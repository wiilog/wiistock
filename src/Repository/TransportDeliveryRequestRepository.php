<?php

namespace App\Repository;

use App\Entity\TransportDeliveryRequest;
use Doctrine\ORM\EntityRepository;

/**
 * @method TransportDeliveryRequest|null find($id, $lockMode = null, $lockVersion = null)
 * @method TransportDeliveryRequest|null findOneBy(array $criteria, array $orderBy = null)
 * @method TransportDeliveryRequest[]    findAll()
 * @method TransportDeliveryRequest[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TransportDeliveryRequestRepository extends EntityRepository {}
