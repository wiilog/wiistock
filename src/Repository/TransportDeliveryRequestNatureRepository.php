<?php

namespace App\Repository;

use App\Entity\TransportDeliveryRequestNature;
use Doctrine\ORM\EntityRepository;

/**
 * @method TransportDeliveryRequestNature|null find($id, $lockMode = null, $lockVersion = null)
 * @method TransportDeliveryRequestNature|null findOneBy(array $criteria, array $orderBy = null)
 * @method TransportDeliveryRequestNature[]    findAll()
 * @method TransportDeliveryRequestNature[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TransportDeliveryRequestNatureRepository extends EntityRepository {}
