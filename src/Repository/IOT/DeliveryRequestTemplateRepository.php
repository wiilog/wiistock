<?php

namespace App\Repository\IOT;

use App\Entity\IOT\DeliveryRequestTemplate;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\EntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method DeliveryRequestTemplate|null find($id, $lockMode = null, $lockVersion = null)
 * @method DeliveryRequestTemplate|null findOneBy(array $criteria, array $orderBy = null)
 * @method DeliveryRequestTemplate[]    findAll()
 * @method DeliveryRequestTemplate[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class DeliveryRequestTemplateRepository extends EntityRepository
{
}
