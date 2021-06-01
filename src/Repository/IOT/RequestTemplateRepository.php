<?php

namespace App\Repository\IOT;

use App\Entity\IOT\RequestTemplate;
use Doctrine\ORM\EntityRepository;

/**
 * @method RequestTemplate|null find($id, $lockMode = null, $lockVersion = null)
 * @method RequestTemplate|null findOneBy(array $criteria, array $orderBy = null)
 * @method RequestTemplate[]    findAll()
 * @method RequestTemplate[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class RequestTemplateRepository extends EntityRepository
{
}
