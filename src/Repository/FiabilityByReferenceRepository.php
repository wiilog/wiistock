<?php

namespace App\Repository;

use App\Entity\FiabilityByReference;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method FiabilityByReference|null find($id, $lockMode = null, $lockVersion = null)
 * @method FiabilityByReference|null findOneBy(array $criteria, array $orderBy = null)
 * @method FiabilityByReference[]    findAll()
 * @method FiabilityByReference[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class FiabilityByReferenceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FiabilityByReference::class);
    }
}
