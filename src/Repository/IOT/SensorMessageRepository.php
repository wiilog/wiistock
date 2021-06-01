<?php

namespace App\Repository\IOT;

use App\Entity\IOT\SensorMessage;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\EntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method SensorMessage|null find($id, $lockMode = null, $lockVersion = null)
 * @method SensorMessage|null findOneBy(array $criteria, array $orderBy = null)
 * @method SensorMessage[]    findAll()
 * @method SensorMessage[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class SensorMessageRepository extends EntityRepository
{
}
