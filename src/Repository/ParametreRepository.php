<?php

namespace App\Repository;

use App\Entity\Parametre;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method Parametre|null find($id, $lockMode = null, $lockVersion = null)
 * @method Parametre|null findOneBy(array $criteria, array $orderBy = null)
 * @method Parametre[]    findAll()
 * @method Parametre[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ParametreRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, Parametre::class);
    }

}
