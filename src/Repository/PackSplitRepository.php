<?php

namespace App\Repository;

use App\Entity\PackSplit;
use Doctrine\ORM\EntityRepository;

/**
 * @method PackSplit|null find($id, $lockMode = null, $lockVersion = null)
 * @method PackSplit|null findOneBy(array $criteria, array $orderBy = null)
 * @method PackSplit[]    findAll()
 * @method PackSplit[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PackSplitRepository extends EntityRepository
{
}
