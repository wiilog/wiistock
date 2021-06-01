<?php

namespace App\Repository\IOT;

use App\Entity\IOT\SensorProfile;
use Doctrine\ORM\EntityRepository;

/**
 * @method SensorProfile|null find($id, $lockMode = null, $lockVersion = null)
 * @method SensorProfile|null findOneBy(array $criteria, array $orderBy = null)
 * @method SensorProfile[]    findAll()
 * @method SensorProfile[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class SensorProfileRepository extends EntityRepository
{
}
