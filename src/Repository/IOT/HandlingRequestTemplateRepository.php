<?php

namespace App\Repository\IOT;

use App\Entity\IOT\HandlingRequestTemplate;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\EntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method HandlingRequestTemplate|null find($id, $lockMode = null, $lockVersion = null)
 * @method HandlingRequestTemplate|null findOneBy(array $criteria, array $orderBy = null)
 * @method HandlingRequestTemplate[]    findAll()
 * @method HandlingRequestTemplate[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class HandlingRequestTemplateRepository extends EntityRepository
{
}
