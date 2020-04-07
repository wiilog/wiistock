<?php

namespace App\Repository;

use App\Entity\ColumnHidden;
use Doctrine\ORM\EntityRepository;

/**
 * @method ColumnHidden|null find($id, $lockMode = null, $lockVersion = null)
 * @method ColumnHidden|null findOneBy(array $criteria, array $orderBy = null)
 * @method ColumnHidden[]    findAll()
 * @method ColumnHidden[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ColumnHiddenRepository extends EntityRepository
{
}
