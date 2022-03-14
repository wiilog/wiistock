<?php

namespace App\Repository;

use App\Entity\TransportDeliveryOrderPack;
use Doctrine\ORM\EntityRepository;

/**
 * @method TransportDeliveryOrderPack|null find($id, $lockMode = null, $lockVersion = null)
 * @method TransportDeliveryOrderPack|null findOneBy(array $criteria, array $orderBy = null)
 * @method TransportDeliveryOrderPack[]    findAll()
 * @method TransportDeliveryOrderPack[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TransportDeliveryOrderPackRepository extends EntityRepository {}
