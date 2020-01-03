<?php

namespace App\Repository;

use App\Entity\CategorieCL;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;

/**
 * @method CategorieCL|null find($id, $lockMode = null, $lockVersion = null)
 * @method CategorieCL|null findOneBy(array $criteria, array $orderBy = null)
 * @method CategorieCL[]    findAll()
 * @method CategorieCL[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CategorieCLRepository extends ServiceEntityRepository
{

    public function findOneByLabel($label)
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "SELECT c
            FROM App\Entity\CategorieCL c
            WHERE c.label = :label
           "
        )->setParameter('label', $label);

        return $query->getOneOrNullResult();
    }

}
