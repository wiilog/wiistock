<?php

namespace App\Repository;

use App\Entity\ColumnHidden;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Persistence\ManagerRegistry;

/**
 * @method ColumnHidden|null find($id, $lockMode = null, $lockVersion = null)
 * @method ColumnHidden|null findOneBy(array $criteria, array $orderBy = null)
 * @method ColumnHidden[]    findAll()
 * @method ColumnHidden[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ColumnHiddenRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ColumnHidden::class);
    }

}
