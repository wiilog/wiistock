<?php

namespace App\Repository\Transport;

use App\Entity\Transport\TemperatureRange;
use Doctrine\ORM\EntityRepository;

/**
 * @method TemperatureRange|null find($id, $lockMode = null, $lockVersion = null)
 * @method TemperatureRange|null findOneBy(array $criteria, array $orderBy = null)
 * @method TemperatureRange[]    findAll()
 * @method TemperatureRange[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TemperatureRangeRepository extends EntityRepository {}
