<?php

namespace App\Repository;

use App\Entity\DispatchPack;
use Doctrine\ORM\EntityRepository;

/**
 * @method DispatchPack|null find($id, $lockMode = null, $lockVersion = null)
 * @method DispatchPack|null findOneBy(array $criteria, array $orderBy = null)
 * @method DispatchPack[]    findAll()
 * @method DispatchPack[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class DispatchPackRepository extends EntityRepository {

    /**
     * @param int[] $dispatchIds
     * @return array
     */
    public function getMobilePacksFromDispatches(array $dispatchIds) {
        $queryBuilder = $this->createQueryBuilder('dispatch_pack');
        $queryBuilder
            ->select('dispatch_pack.id AS id')
            ->addSelect('pack.code AS code')
            ->addSelect('nature.id AS natureId')
            ->addSelect('dispatch_pack.quantity AS quantity')
            ->addSelect('dispatch.id AS dispatchId')
            ->addSelect('packLastLocation.label AS lastLocation')
            ->join('dispatch_pack.pack', 'pack')
            ->join('dispatch_pack.dispatch', 'dispatch')
            ->leftJoin('pack.nature', 'nature')
            ->leftJoin('pack.lastTracking', 'packLastTracking')
            ->leftJoin('packLastTracking.emplacement', 'packLastLocation')
            ->where('dispatch.id IN (:dispatchIds)')
            ->andWhere('dispatch_pack.treated = false')
            ->setParameter('dispatchIds', $dispatchIds);
        return $queryBuilder
            ->getQuery()
            ->getResult();
    }
}
