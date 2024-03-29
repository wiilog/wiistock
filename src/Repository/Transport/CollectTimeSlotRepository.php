<?php

namespace App\Repository\Transport;

use App\Entity\Transport\CollectTimeSlot;
use Doctrine\ORM\EntityRepository;

/**
 * @method CollectTimeSlot|null find($id, $lockMode = null, $lockVersion = null)
 * @method CollectTimeSlot|null findOneBy(array $criteria, array $orderBy = null)
 * @method CollectTimeSlot[]    findAll()
 * @method CollectTimeSlot[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CollectTimeSlotRepository extends EntityRepository {}
