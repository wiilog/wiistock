<?php

namespace App\Repository\IOT;

use App\Entity\IOT\CollectRequestTemplate;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\EntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method CollectRequestTemplate|null find($id, $lockMode = null, $lockVersion = null)
 * @method CollectRequestTemplate|null findOneBy(array $criteria, array $orderBy = null)
 * @method CollectRequestTemplate[]    findAll()
 * @method CollectRequestTemplate[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CollectRequestTemplateRepository extends EntityRepository
{
}
