<?php

namespace App\Repository;

use App\Entity\ReserveType;
use Doctrine\ORM\EntityRepository;

/**
 * @method ReserveType|null find($id, $lockMode = null, $lockVersion = null)
 * @method ReserveType|null findOneBy(array $criteria, array $orderBy = null)
 * @method ReserveType[]    findAll()
 * @method ReserveType[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ReserveTypeRepository extends EntityRepository
{
    public function getActiveReserveType(): array {
        return $this->createQueryBuilder("reserve_type")
            ->select("reserve_type.id AS id")
            ->addSelect("reserve_type.label AS label")
            ->addSelect("reserve_type.defaultReserveType AS defaultReserve")
            ->andWhere("reserve_type.active = true")
            ->getQuery()
            ->getArrayResult();
    }
}
