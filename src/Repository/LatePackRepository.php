<?php

namespace App\Repository;

use App\Entity\LatePack;
use Doctrine\ORM\EntityRepository;

/**
 * @method LatePack|null find($id, $lockMode = null, $lockVersion = null)
 * @method LatePack|null findOneBy(array $criteria, array $orderBy = null)
 * @method LatePack[]    findAll()
 * @method LatePack[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class LatePackRepository extends EntityRepository
{

    public function clearTable(): void {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "DELETE
            FROM App\Entity\LatePack l
           "
        );
        $query->execute();
    }

    public function findAllForDatatable() {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "SELECT l.delay, l.LU as pack, l.date, l.emp as location
            FROM App\Entity\LatePack l
           "
        );
        return $query->execute();
    }
}
