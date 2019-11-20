<?php

namespace App\Repository;

use App\Entity\ReceptionTraca;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method ReceptionTraca|null find($id, $lockMode = null, $lockVersion = null)
 * @method ReceptionTraca|null findOneBy(array $criteria, array $orderBy = null)
 * @method ReceptionTraca[]    findAll()
 * @method ReceptionTraca[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ReceptionTracaRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, ReceptionTraca::class);
    }
}
