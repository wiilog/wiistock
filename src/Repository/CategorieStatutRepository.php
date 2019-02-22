<?php

namespace App\Repository;

use App\Entity\CategorieStatut;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method CategorieStatut|null find($id, $lockMode = null, $lockVersion = null)
 * @method CategorieStatut|null findOneBy(array $criteria, array $orderBy = null)
 * @method CategorieStatut[]    findAll()
 * @method CategorieStatut[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CategorieStatutRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, CategorieStatut::class);
    }
}
