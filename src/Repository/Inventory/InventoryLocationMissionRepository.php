<?php

namespace App\Repository\Inventory;

use App\Entity\Inventory\InventoryLocationMission;
use App\Entity\Inventory\InventoryMission;
use App\Helper\QueryBuilderHelper;
use Doctrine\ORM\EntityRepository;
use Symfony\Component\HttpFoundation\InputBag;


class InventoryLocationMissionRepository extends EntityRepository {

    public function getInventoryLocationZones(): array {
        $queryBuilder = $this->createQueryBuilder('inventoryLocationZone');

        $queryBuilder
            ->select('inventoryLocationZone.id AS id')
            ->addSelect('location.id AS location_id')
            ->addSelect('location.label AS location_label')
            ->addSelect('inventoryMission.id AS mission_id')
            ->addSelect('locationZone.id AS zone_id')
            ->addSelect('locationZone.name AS zone_label')
            ->addSelect('inventoryLocationZone.done AS done')
            ->leftJoin('inventoryLocationZone.inventoryMission', 'inventoryMission')
            ->leftJoin('inventoryLocationZone.location', 'location')
            ->leftJoin('location.zone', 'locationZone');

        return $queryBuilder
            ->getQuery()
            ->getArrayResult();
    }

    public function getInventoryLocationMissionsByMission($missionId): array
    {
        $queryBuilder = $this->createQueryBuilder('inventoryLocationMission')
            ->leftJoin('inventoryLocationMission.inventoryMission', 'inventoryMission')
            ->andWhere('inventoryMission.id = :missionId')
            ->setParameter("missionId", $missionId);

        return $queryBuilder
            ->getQuery()
            ->getResult();
    }

    public function getDataByMission(InventoryMission $mission, InputBag $params  = null, array $filters = []) : array {
        $start = $params->get('start') ?? 0;
        $length = $params->get('length') ?? 5;
        $search = $params->all('search') ?? null;

        $queryBuilder = $this->createQueryBuilder('inventory_location_mission');
        $exprBuilder = $queryBuilder->expr();

        $queryBuilder
            ->leftJoin('inventory_location_mission.inventoryMission', 'join_inventoryMission')
            ->leftJoin('inventory_location_mission.location', 'join_location')
            ->leftJoin('join_location.zone', 'join_zone');

        if ($mission->isDone()) {
            $queryBuilder
                ->leftJoin('inventory_location_mission.operator', 'join_user');
        }

        $queryBuilder
            ->andWhere('join_inventoryMission.id = :mission')
            ->orderBy('inventory_location_mission.id')
            ->setParameter('mission', $mission->getId());
        $total = QueryBuilderHelper::count($queryBuilder, "join_location");

        // search
        if (!empty($search) && !empty($search['value'])) {
            $value = $search['value'];
            if ($mission->isDone()) {
                $queryBuilder
                    ->andWhere($exprBuilder->orX(
                        'join_zone.name LIKE :search',
                        'join_location.label LIKE :search',
                        'join_user.username LIKE :search',
                    ))
                    ->setParameter('search', "%$value%");
            } else {
                $queryBuilder
                    ->andWhere($exprBuilder->orX(
                        'join_zone.name LIKE :search',
                        'join_location.label LIKE :search'
                    ))
                    ->setParameter('search', "%$value%");
            }
        }

        // filtres sup
        foreach ($filters as $filter) {
            switch ($filter['field']) {
                case 'dateMin':
                    $queryBuilder
                        ->andWhere('inventory_location_mission.scannedAt >= :dateMin')
                        ->setParameter('dateMin', $filter['value'] . " 00:00:00");
                    break;
                case 'dateMax':
                    $queryBuilder
                        ->andWhere('inventory_location_mission.scannedAt <= :dateMax')
                        ->setParameter('dateMax', $filter['value'] . " 23:59:59");
                    break;
            }
        }

        $countQuery = QueryBuilderHelper::count($queryBuilder, "join_location");

        if (!empty($params->all('order'))) {
            $order = $params->all('order')[0]['dir'];
            if (!empty($order)) {
                $column = $params->all('columns')[$params->all('order')[0]['column']]['data'];
                switch ($column) {
                    case 'percentage':
                        if ($mission->isDone()) {
                            $queryBuilder
                                ->addOrderBy('inventory_location_mission.percentage', $order);
                        }
                        break;
                    case 'zone':
                        $queryBuilder
                            ->addOrderBy('join_zone.name', $order);
                        break;
                    case 'reference':
                        if ($mission->isDone()) {
                            $queryBuilder
                                ->addOrderBy('join_referenceArticle.reference', $order);
                        }
                        break;
                    case 'date':
                        if ($mission->isDone()) {
                            $queryBuilder
                                ->addOrderBy('inventory_location_mission.scannedAt', $order);
                        }
                        break;
                    case 'operator':
                        if ($mission->isDone()) {
                            $queryBuilder
                                ->addOrderBy('join_user.username', $order);
                        }
                        break;
                    case 'location':
                        $queryBuilder
                            ->addOrderBy('inventory_location_mission.location', $order);
                        break;
                }
            }
        }
        $queryBuilder->setFirstResult($start);
        $queryBuilder->setMaxResults($length);

        return [
            "data" => $queryBuilder->getQuery()->getResult(),
            'recordsFiltered' => $countQuery,
            'recordsTotal' => $total
        ];
    }

    public function getArticlesByInventoryLocationMission(InventoryLocationMission $inventoryLocationMission, InputBag $params  = null): array {
        $start = $params->get('start') ?? 0;
        $length = $params->get('length') ?? 15;
        $search = $params->all('search') ?? null;

        $queryBuilder = $this->createQueryBuilder('inventoryLocationMission');
        $exprBuilder = $queryBuilder->expr();

        $queryBuilder
            ->select('join_article.barCode AS barcode')
            ->addSelect('join_article.RFIDtag AS RFIDtag')
            ->addSelect('join_referenceArticle.reference AS reference')
            ->join('inventoryLocationMission.articles', 'join_article')
            ->join('join_article.articleFournisseur', 'join_articleFournisseur')
            ->join('join_articleFournisseur.referenceArticle', 'join_referenceArticle')
            ->andWhere('inventoryLocationMission.id = :inventoryLocationMission')
            ->orderBy('join_referenceArticle.reference')
            ->addOrderBy('join_article.id')
            ->setParameter('inventoryLocationMission', $inventoryLocationMission)
            ->getQuery()
            ->getArrayResult();

        $queryBuilder->setFirstResult($start);
        $queryBuilder->setMaxResults($length);

        // count before searching
        $total = QueryBuilderHelper::count($queryBuilder, "join_article");

        // search
        if (!empty($search) && !empty($search['value'])) {
            $value = $search['value'];
            $queryBuilder
                ->andWhere($exprBuilder->orX(
                    'join_referenceArticle.reference LIKE :search',
                    'join_article.RFIDtag LIKE :search',
                    'join_article.barCode LIKE :search',
                ))
                ->setParameter('search', "%$value%");
        }

        $countQuery = QueryBuilderHelper::count($queryBuilder, "join_article");

        return [
            "data" => $queryBuilder->getQuery()->getResult(),
            'recordsFiltered' => $countQuery,
            'recordsTotal' => $total
        ];
    }
}
