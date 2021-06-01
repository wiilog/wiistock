<?php

namespace App\Repository\IOT;

use App\Entity\IOT\SensorWrapper;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\EntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method SensorWrapper|null find($id, $lockMode = null, $lockVersion = null)
 * @method SensorWrapper|null findOneBy(array $criteria, array $orderBy = null)
 * @method SensorWrapper[]    findAll()
 * @method SensorWrapper[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class SensorWrapperRepository extends EntityRepository
{
}
