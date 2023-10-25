<?php

namespace App\Repository\Fields;

use App\Entity\Fields\FixedFieldByType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<FixedFieldByType>
 *
 * @method FixedFieldByType|null find($id, $lockMode = null, $lockVersion = null)
 * @method FixedFieldByType|null findOneBy(array $criteria, array $orderBy = null)
 * @method FixedFieldByType[]    findAll()
 * @method FixedFieldByType[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class FixedFieldRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FixedFieldByType::class);
    }

}
