<?php

namespace App\Repository;

use App\Entity\PackAcheminement;
use Doctrine\ORM\EntityRepository;

/**
 * @method PackAcheminement|null find($id, $lockMode = null, $lockVersion = null)
 * @method PackAcheminement|null findOneBy(array $criteria, array $orderBy = null)
 * @method PackAcheminement[]    findAll()
 * @method PackAcheminement[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PackAcheminementRepository extends EntityRepository {

    /**
     * @param int[] $dispatchIds
     * @return array
     */
    public function getMobilePacksFromDispatches(array $dispatchIds) {
        $queryBuilder = $this->createQueryBuilder('pack_dispatch');
        $queryBuilder
            ->select('pack_dispatch.id AS id')
            ->addSelect('pack.code AS code')
            ->addSelect('nature.id AS natureId')
            ->addSelect('pack_dispatch.quantity AS quantity')
            ->addSelect('dispatch.id AS dispatchId')
            ->join('pack_dispatch.pack', 'pack')
            ->join('pack_dispatch.acheminement', 'dispatch')
            ->leftJoin('pack.nature', 'nature')
            ->where('dispatch.id IN (:dispatchIds)')
            ->setParameter('dispatchIds', $dispatchIds);
        return $queryBuilder
            ->getQuery()
            ->getResult();
    }
}
