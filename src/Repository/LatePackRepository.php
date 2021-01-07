<?php

namespace App\Repository;

use App\Entity\LatePack;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method LatePack|null find($id, $lockMode = null, $lockVersion = null)
 * @method LatePack|null findOneBy(array $criteria, array $orderBy = null)
 * @method LatePack[]    findAll()
 * @method LatePack[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class LatePackRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LatePack::class);
    }

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
            "SELECT l.delay, l.colis as pack, l.date, l.emp as location
            FROM App\Entity\LatePack l
           "
        );
        return $query->execute();
    }
}
