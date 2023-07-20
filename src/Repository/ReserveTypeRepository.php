<?php

namespace App\Repository;

use App\Entity\ReserveType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ReserveType>
 *
 * @method ReserveType|null find($id, $lockMode = null, $lockVersion = null)
 * @method ReserveType|null findOneBy(array $criteria, array $orderBy = null)
 * @method ReserveType[]    findAll()
 * @method ReserveType[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ReserveTypeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ReserveType::class);
    }

    public function save(ReserveType $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(ReserveType $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
