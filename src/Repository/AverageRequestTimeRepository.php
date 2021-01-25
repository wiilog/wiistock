<?php

namespace App\Repository;

use App\Entity\AverageRequestTime;
use Doctrine\ORM\EntityRepository;

/**
 * @method AverageRequestTime|null find($id, $lockMode = null, $lockVersion = null)
 * @method AverageRequestTime|null findOneBy(array $criteria, array $orderBy = null)
 * @method AverageRequestTime[]    findAll()
 * @method AverageRequestTime[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class AverageRequestTimeRepository extends EntityRepository
{
}
