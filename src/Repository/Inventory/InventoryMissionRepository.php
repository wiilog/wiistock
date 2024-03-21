<?php

namespace App\Repository\Inventory;

use App\Entity\Article;
use App\Entity\Inventory\InventoryEntry;
use App\Entity\Inventory\InventoryMission;
use App\Entity\ReferenceArticle;
use App\Helper\QueryBuilderHelper;
use DateTime;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\HttpFoundation\InputBag;
use WiiCommon\Helper\Stream;

/**
 * @method InventoryMission|null find($id, $lockMode = null, $lockVersion = null)
 * @method InventoryMission|null findOneBy(array $criteria, array $orderBy = null)
 * @method InventoryMission[]    findAll()
 * @method InventoryMission[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class InventoryMissionRepository extends EntityRepository {

    const DtToDbLabels = [
        'start' => 'startPrevDate',
        'end' => 'endPrevDate',
        'name' => 'name',
        'requester' => 'requester',
        'type' => 'type',
    ];

    public function getInventoriableArticles(): array {
        $now = (new DateTime())->format("Y-m-d");

        $queryBuilder = $this->createQueryBuilder("inventory_mission");

        return $queryBuilder
            ->select('inventory_mission.id AS mission_id')
            ->addSelect("inventory_mission.startPrevDate AS mission_start")
            ->addSelect("inventory_mission.endPrevDate AS mission_end")
            ->addSelect("inventory_mission.name AS mission_name")
            ->addSelect("inventory_mission.done AS done")
            ->addSelect("inventory_mission.type AS type")
            ->addSelect("join_articleReferenceArticle.reference AS reference")
            ->addSelect("join_article.barCode AS barCode")
            ->addSelect("join_logisticUnit.code AS logistic_unit_code")
            ->addSelect("join_logisticUnit.id AS logistic_unit_id")
            ->addSelect("join_nature.label AS logistic_unit_nature")
            ->addSelect("join_articleLocation.label AS location")
            ->addSelect("0 AS is_ref")
            ->innerJoin('inventory_mission.articles', 'join_article')
            ->innerJoin('join_article.emplacement', 'join_articleLocation')
            ->innerJoin('join_article.articleFournisseur', 'join_supplierArticle')
            ->innerJoin('join_supplierArticle.referenceArticle', 'join_articleReferenceArticle')
            ->leftJoin('join_article.currentLogisticUnit', 'join_logisticUnit')
            ->leftJoin('join_logisticUnit.nature', 'join_nature')
            ->leftJoin('inventory_mission.entries', 'inventory_entry_articles', Join::WITH, 'inventory_entry_articles.article = join_article')
            ->andWhere("inventory_entry_articles.id IS NULL")
            ->andWhere("inventory_mission.startPrevDate <= :now")
            ->andWhere("inventory_mission.endPrevDate >= :now")
            ->setParameter("now", $now)
            ->getQuery()
            ->getResult();
    }

    public function getInventoriableReferences(): array {
        $now = (new DateTime())->format("Y-m-d");

        $queryBuilder = $this->createQueryBuilder("inventory_mission");

        return $queryBuilder
            ->select('inventory_mission.id AS mission_id')
            ->addSelect("inventory_mission.startPrevDate AS mission_start")
            ->addSelect("inventory_mission.endPrevDate AS mission_end")
            ->addSelect("inventory_mission.name AS mission_name")
            ->addSelect("inventory_mission.done AS done")
            ->addSelect("inventory_mission.type AS type")
            ->addSelect("join_referenceArticle.reference AS reference")
            ->addSelect("join_referenceArticle.barCode AS barCode")
            ->addSelect("join_logisticUnit.code AS logistic_unit_code")
            ->addSelect("join_logisticUnit.id AS logistic_unit_id")
            ->addSelect("join_nature.label AS logistic_unit_nature")
            ->addSelect("join_referenceArticleLocation.label AS location")
            ->addSelect("1 AS is_ref")
            ->innerJoin('inventory_mission.refArticles', 'join_referenceArticle')
            ->innerJoin('join_referenceArticle.emplacement', 'join_referenceArticleLocation')
            ->leftJoin('inventory_mission.entries', 'inventory_entry_references', Join::WITH, 'inventory_entry_references.refArticle = join_referenceArticle')
            ->andWhere("inventory_mission.startPrevDate <= :now")
            ->andWhere("inventory_mission.endPrevDate >= :now")
            ->andWhere("inventory_entry_references.id IS NULL")
            ->setParameter("now", $now)
            ->getQuery()
            ->getResult();
    }

	public function countAnomaliesByMission($mission)
    {
        return $this->createQueryBuilder('mission')
            ->select('COUNT(entry)')
            ->join('mission.entries', 'entry')
            ->andWhere('mission = :mission')
            ->andWhere('entry.anomaly = true')
            ->setParameter('mission', $mission)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findRefByMissionAndParamsAndFilters(InventoryMission $mission, InputBag $params = null, array $filters = []): array {
        $em = $this->getEntityManager();
        $qb = $em->createQueryBuilder();
        $qb
            ->select('ra')
            ->from('App\Entity\ReferenceArticle', 'ra')
            ->join('ra.inventoryMissions', 'm')
            ->leftJoin('ra.inventoryEntries', 'ie', Join::WITH, 'ie.mission = m')
            ->where('m = :mission')
            ->setParameter('mission', $mission);

        $countQuery = $countTotal = QueryBuilderHelper::count($qb, 'ra');

        // filtres sup
        foreach ($filters as $filter) {
            switch ($filter['field']) {
                case 'anomaly':
                    if ($filter['value'] == 'true') {
                        $qb->andWhere('ie.anomaly = 1');
                    }
                    else if ($filter['value'] == 'false') {
                        $qb->andWhere('ie.anomaly = 0');
                    }
                    break;
                case 'dateMin':
                    $qb
                        ->andWhere('ie.date >= :dateMin')
                        ->setParameter('dateMin', $filter['value'] . " 00:00:00");
                    break;
                case 'dateMax':
                    $qb
                        ->andWhere('ie.date <= :dateMax')
                        ->setParameter('dateMax', $filter['value'] . " 23:59:59");
                    break;
            }
        }

        // filtre recherche
        if (!empty($params)) {
            if (!empty($params->all('search'))) {
                $search = $params->all('search')['value'];
                if (!empty($search)) {
                    $qb
                        ->andWhere('ra.libelle LIKE :value OR ra.reference LIKE :value OR ra.barCode LIKE :value')
                        ->setParameter('value', '%' . $search . '%');
                }
                $countQuery = QueryBuilderHelper::count($qb, 'ra');
            }

            if (!empty($params->all('order'))) {
                $order = $params->all('order')[0]['dir'];

                if (!empty($order)) {
                    $column = $params->all('columns')[$params->all('order')[0]['column']]['data'];

                    if ($column === 'reference') {
                        $qb->orderBy('ra.reference', $order);
                    } else if ($column === 'barcode') {
                        $qb->orderBy('ra.barCode', $order);
                    } else if ($column === 'label') {
                        $qb->orderBy('ra.libelle', $order);
                    } else if ($column === 'location') {
                        $qb->leftJoin('ra.emplacement', 'join_location')
                            ->orderBy('join_location.label', $order);
                    } else if ($column === 'date') {
                        $qb->orderBy('ie.date', $order);
                    } else  if ($column === 'anomaly') {
                        $qb->orderBy('ie.anomaly', $order);
                    } else  if ($column === 'stockQuantity') {
                        $qb->orderBy('ra.quantiteStock', $order);
                    } else  if ($column === 'countedQuantity') {
                        $qb->orderBy('ie.quantity', $order);
                    }
                }
            }

            if ($params->getInt('start')) {
                $qb->setFirstResult($params->getInt('start'));
            }
            if ($params->getInt('length')) {
                $qb->setMaxResults($params->getInt('length'));
            }
        }

        $query = $qb->getQuery();

        return [
            'data' => $query?->getResult(),
            'count' => $countQuery,
            'total' => $countTotal,
        ];
    }

    public function findArtByMissionAndParamsAndFilters(InventoryMission $mission, InputBag $params = null, array $filters = []): array {
        $em = $this->getEntityManager();
        $qb = $em->createQueryBuilder();

        $qb
            ->select('a')
            ->from('App\Entity\Article', 'a')
            ->join('a.inventoryMissions', 'm')
            ->leftJoin('a.inventoryEntries', 'ie', Join::WITH, 'ie.mission = m')
            ->where('m = :mission')
            ->setParameter('mission', $mission);

        $countQuery = $countTotal = QueryBuilderHelper::count($qb, 'a');

        // filtres sup
        foreach ($filters as $filter) {
            switch ($filter['field']) {
                case 'anomaly':
                    if ($filter['value'] == 'true') {
                        $qb->andWhere('ie.anomaly = 1');
                    }
                    else if ($filter['value'] == 'false') {
                        $qb->andWhere('ie.anomaly = 0');
                    }
                    break;
                case 'dateMin':
                    $qb
                        ->andWhere('ie.date >= :dateMin')
                        ->setParameter('dateMin', $filter['value'] . " 00:00:00");
                    break;
                case 'dateMax':
                    $qb
                        ->andWhere('ie.date <= :dateMax')
                        ->setParameter('dateMax', $filter['value'] . " 23:59:59");
                    break;
            }
        }

        // filtre recherche
        if (!empty($params)) {
            if (!empty($params->all('search'))) {
                $search = $params->all('search')['value'];
                if (!empty($search)) {
                    $qb
                        ->andWhere('
                            a.label LIKE :value
                            OR a.reference LIKE :value
                            OR a.barCode LIKE :value
                            OR join_emplacement.label LIKE :value
                            OR join_logisticUnit.code LIKE :value
                        ')
                        ->leftJoin('a.emplacement', 'join_emplacement')
                        ->leftJoin('a.currentLogisticUnit', 'join_logisticUnit')
                        ->setParameter('value', '%' . $search . '%');
                }
                $countQuery = QueryBuilderHelper::count($qb, 'a');
            }

            if (!empty($params->all('order'))) {
                $order = $params->all('order')[0]['dir'];

                if (!empty($order)) {
                    $column = $params->all('columns')[$params->all('order')[0]['column']]['data'];

                    if ($column === 'reference') {
                        $qb->orderBy('a.reference', $order);
                    } else if ($column === 'barcode') {
                        $qb->orderBy('a.barCode', $order);
                    } else if ($column === 'label') {
                        $qb->orderBy('a.label', $order);
                    } else if ($column === 'logisticUnit') {
                        $qb->leftJoin('a.currentLogisticUnit', 'join_logisticUnit')
                            ->orderBy('join_logisticUnit.code', $order);
                    } else if ($column === 'location') {
                        $qb->leftJoin('a.emplacement', 'join_location')
                            ->orderBy('join_location.label', $order);
                    } else if ($column === 'date') {
                        $qb->orderBy('ie.date', $order);
                    } else  if ($column === 'anomaly') {
                        $qb->orderBy('ie.anomaly', $order);
                    } else  if ($column === 'stockQuantity') {
                        $qb->orderBy('a.quantite', $order);
                    } else  if ($column === 'countedQuantity') {
                        $qb->orderBy('ie.quantity', $order);
                    }
                }
            }

            if ($params->getInt('start')) {
                $qb->setFirstResult($params->getInt('start'));
            }
            if ($params->getInt('length')) {
                $qb->setMaxResults($params->getInt('length'));
            }
        }

        $query = $qb->getQuery();

        return [
            'data' => $query?->getResult(),
            'count' => $countQuery,
            'total' => $countTotal,
        ];
    }

    public function findMissionsByParamsAndFilters(InputBag $params, array $filters): array {
        $qb = $this->createQueryBuilder("im");

        $countTotal = QueryBuilderHelper::count($qb, 'im');

        // filtres sup
        foreach ($filters as $filter) {
            switch ($filter['field']) {
                case 'anomaly':
                    $anomalyDQL = $this->getEntityManager()
                        ->createQueryBuilder()
                        ->select('COUNT(entry)')
                        ->from(InventoryEntry::class, 'entry')
                        ->andWhere('entry.mission = im AND entry.anomaly = 1')
                        ->getQuery()
                        ->getDQL();
                    if ($filter['value'] == 'true') {
                        $qb->andWhere("($anomalyDQL) > 0");
                    }
                    else if ($filter['value'] == 'false') {
                        $qb->andWhere("($anomalyDQL) = 0");
                    }
                    break;
                case 'dateMin':
                    $qb
                        ->andWhere('im.endPrevDate >= :dateMin')
                        ->setParameter('dateMin', $filter['value'] . " 00:00:00");
                    break;
                case 'dateMax':
                    $qb
                        ->andWhere('im.startPrevDate <= :dateMax')
                        ->setParameter('dateMax', $filter['value'] . " 23:59:59");
                    break;
                case 'multipleTypes':
                    $types = explode(',', $filter['value']);
                    $types = Stream::from($types)
                        ->map(fn(string $type) => strtok($type, ':'))
                        ->toArray();
                    $qb
                        ->andWhere('im.type IN (:type_filter)')
                        ->setParameter('type_filter', $types);
                    break;
            }
        }

        if (!empty($params)) {
            if (!empty($params->all('order'))) {
                $order = $params->all('order')[0]['dir'];
                if (!empty($order)) {
                    $column = self::DtToDbLabels[$params->all('columns')[$params->all('order')[0]['column']]['data']];
                    if ($column === 'requester') {
                        $qb
                            ->leftJoin('im.requester', 'order_requester')
                            ->orderBy('order_requester.username', $order);
                    } else {
                        $qb->orderBy('im.' . $column, $order);
                    }
                }
            }
        }

        // compte éléments filtrés
        $countFiltered = QueryBuilderHelper::count($qb, 'im');

        if ($params->getInt('start')) {
            $qb->setFirstResult($params->getInt('start'));
        }
        if ($params->getInt('length')) {
            $qb->setMaxResults($params->getInt('length'));
        }

        $query = $qb->getQuery();

        return [
            'data' => $query?->getResult(),
            'count' => $countFiltered,
            'total' => $countTotal,
        ];
    }

	/**
	 * @param string $date
	 * @return InventoryMission|null
	 */
    public function findFirstByStartDate($date)
    {
        $result = $this->createQueryBuilder('mission')
            ->andWhere('mission.startPrevDate = :date')
            ->setParameter('date', $date)
            ->getQuery()
            ->getResult();

        return $result ? $result[0] : null;
    }

    public function countByRefAndDates(ReferenceArticle $ref, DateTime $startDate, DateTime $endDate): int {
        return $this->createQueryBuilderMissionInBracket($startDate, $endDate)
            ->join('mission.refArticles', 'refArticle')
            ->andWhere('refArticle = :refArt')
            ->setParameter('refArt', $ref)
            ->select('COUNT(mission)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countByArtAndDates(Article $art, DateTime $startDate, DateTime $endDate): int {
        return $this->createQueryBuilderMissionInBracket($startDate, $endDate)
            ->join('mission.articles', 'article')
            ->andWhere('article = :art')
            ->setParameter('art', $art)
            ->select('COUNT(mission)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    private function createQueryBuilderMissionInBracket(DateTime $startDate, DateTime $endDate): QueryBuilder {
        $queryBuilder = $this->createQueryBuilder('mission');
        $exprBuilder = $queryBuilder->expr();

        // On teste si les dates ne se chevauchent pas
        return $queryBuilder
            ->where($exprBuilder->orX(
                $exprBuilder->between('mission.startPrevDate', ':startDate', ':endDate'),
                $exprBuilder->between('mission.endPrevDate', ':startDate', ':endDate'),
                $exprBuilder->between(':startDate', 'mission.startPrevDate', 'mission.endPrevDate'),
                $exprBuilder->between(':endDate', 'mission.startPrevDate', 'mission.endPrevDate')
            ))
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate);
    }

    public function getInventoryMissions(): mixed {
        $now = new DateTime('now');

        $queryBuilder = $this->createQueryBuilder('inventoryMission');
        $exprBuilder = $queryBuilder->expr();

        $queryBuilder
            ->select('inventoryMission.id AS id')
            ->addSelect('inventoryMission.startPrevDate AS mission_start')
            ->addSelect('inventoryMission.endPrevDate AS mission_end')
            ->addSelect('inventoryMission.name AS mission_name')
            ->addSelect('inventoryMission.type AS type')
            ->where($exprBuilder->andX(
                'inventoryMission.startPrevDate <= :now',
                'inventoryMission.endPrevDate >= :now',
                'inventoryMission.done IS NULL OR inventoryMission.done = 0'
            ))
            ->setParameter('now', $now->format('Y-m-d'));

        return $queryBuilder
            ->getQuery()
            ->getArrayResult();
    }
}
