<?php

namespace App\Repository;

use App\Entity\NonWorkedDays;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use phpDocumentor\Reflection\Types\Null_;

/**
 * @method NonWorkedDays|null   find($id, $lockMode = null, $lockVersion = null)
 * @method NonWorkedDays|null   findOneBy(array $criteria, array $orderBy = null)
 * @method NonWorkedDays[]      findAll()
 * @method NonWorkedDays[]      findBy(array $criteria, array $orderBy = null, $limite = null, $offset = null)
 */
class NonWorkedDaysRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, NonWorkedDays::class);
    }
}