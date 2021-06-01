<?php

namespace App\Repository\IOT;

use App\Entity\IOT\TriggerAction;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\EntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method TriggerAction|null find($id, $lockMode = null, $lockVersion = null)
 * @method TriggerAction|null findOneBy(array $criteria, array $orderBy = null)
 * @method TriggerAction[]    findAll()
 * @method TriggerAction[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TriggerActionRepository extends EntityRepository
{
}
