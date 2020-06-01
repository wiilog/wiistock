<?php

namespace App\Repository;

use App\Entity\WorkFreeDay;
use Doctrine\ORM\EntityRepository;

/**
 * @method WorkFreeDay|null   find($id, $lockMode = null, $lockVersion = null)
 * @method WorkFreeDay|null   findOneBy(array $criteria, array $orderBy = null)
 * @method WorkFreeDay[]      findAll()
 * @method WorkFreeDay[]      findBy(array $criteria, array $orderBy = null, $limite = null, $offset = null)
 */
class WorkFreeDayRepository extends EntityRepository
{
}