<?php

namespace App\Repository\Inventory;

use App\Entity\Article;
use App\Entity\Inventory\InventoryEntry;
use App\Entity\Inventory\InventoryLocationMission;
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
        'name' => 'name'
    ];

    public function getCurrentMissionRefNotTreated(): mixed {
        $now = new DateTime('now');
        $queryBuilder = $this->createQueryBuilder('inventoryMission');
        $exprBuilder = $queryBuilder->expr();

        $queryBuilder
            ->select('inventoryMission.id AS mission_id')
            ->addSelect('inventoryMission.startPrevDate AS mission_start')
            ->addSelect('inventoryMission.endPrevDate AS mission_end')
            ->addSelect('inventoryMission.name AS mission_name')
            ->addSelect('refArticle.reference AS reference')
            ->addSelect('emplacement.label AS location')
            ->addSelect('1 AS is_ref')
            ->addSelect('inventoryMission.done AS done')
            ->addSelect('inventoryMission.type AS type')
            ->addSelect('inventoryEntry.id AS ieid')
            ->addSelect('refArticle.barCode AS barCode')
            ->join('inventoryMission.refArticles', 'refArticle')
            ->join('refArticle.emplacement', 'emplacement')
            ->leftJoin('inventoryMission.entries', 'inventoryEntry', Join::WITH, 'inventoryEntry.refArticle = refArticle')
            ->where($exprBuilder->andX(
                'inventoryMission.startPrevDate <= :now',
                'inventoryMission.endPrevDate >= :now',
                'inventoryEntry.id IS NULL'
            ))
            ->setParameter('now', $now->format('Y-m-d'));

        return $queryBuilder
            ->getQuery()
            ->execute();
    }

    public function getCurrentMissionArticlesNotTreated(): mixed {
        $now = new DateTime('now');

        $queryBuilder = $this->createQueryBuilder('inventoryMission');
        $exprBuilder = $queryBuilder->expr();

        $queryBuilder
            ->select('inventoryMission.id AS mission_id')
            ->addSelect('inventoryMission.startPrevDate AS mission_start')
            ->addSelect('inventoryMission.endPrevDate AS mission_end')
            ->addSelect('inventoryMission.name AS mission_name')
            ->addSelect('referenceArticle.reference AS reference')
            ->addSelect('article.barCode AS barCode')
            ->addSelect('current_logistic_unit.code as logistic_unit_code')
            ->addSelect('current_logistic_unit.id as logistic_unit_id')
            ->addSelect('nature.label as logistic_unit_nature')
            ->addSelect('emplacement.label AS location')
            ->addSelect('inventoryMission.done AS done')
            ->addSelect('inventoryMission.type AS type')
            ->addSelect('0 AS is_ref')
            ->addSelect('inventoryMission.id AS ied')
            ->join('inventoryMission.articles', 'article')
            ->join('article.emplacement', 'emplacement')
            ->join('article.articleFournisseur', 'articleFournisseur')
            ->leftJoin('article.currentLogisticUnit', 'current_logistic_unit')
            ->leftJoin('current_logistic_unit.nature', 'nature')
            ->join('articleFournisseur.referenceArticle', 'referenceArticle')
            ->leftJoin('inventoryMission.entries', 'inventoryEntry', Join::WITH, 'inventoryEntry.article = article')
            ->where($exprBuilder->andX(
                'inventoryMission.startPrevDate <= :now',
                'inventoryMission.endPrevDate >= :now',
                'inventoryEntry.id IS NULL'
            ))
            ->setParameter('now', $now->format('Y-m-d'));

        return $queryBuilder
            ->getQuery()
            ->execute();
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
                        ->andWhere('a.label LIKE :value OR a.reference LIKE :value OR a.barCode LIKE :value')
                        ->setParameter('value', '%' . $search . '%');
                }
                $countQuery = QueryBuilderHelper::count($qb, 'a');
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
                    $qb->orderBy('im.' . $column, $order);
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
            ))
            ->setParameter('now', $now->format('Y-m-d'));

        return $queryBuilder
            ->getQuery()
            ->getArrayResult();
    }

    public function getInventoryLocationZones(): mixed {
        $entityManager = $this->getEntityManager();
        $queryBuilder = $entityManager->createQueryBuilder()
            ->from(InventoryLocationMission::class, 'inventoryLocationZone');

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

    public function getInventoryLocationMissionsByMission($missionId): mixed {
        $entityManager = $this->getEntityManager();
        $queryBuilder = $entityManager->createQueryBuilder()
            ->select('inventoryLocationMission')
            ->from(InventoryLocationMission::class, 'inventoryLocationMission');

        $queryBuilder
            ->leftJoin('inventoryLocationMission.inventoryMission', 'inventoryMission')
            ->andWhere('inventoryMission.id = :missionId')
            ->setParameter("missionId", $missionId);

        return $queryBuilder
            ->getQuery()
            ->getResult();
    }
    public function getDataByMission(InventoryMission $mission, array $params) : array {
        $search = $params['search'] ?? null;

        $queryBuilder = $this->createQueryBuilder('inventory_location_mission');
        $exprBuilder = $queryBuilder->expr();

        $queryBuilder
            ->select('inventory_location_mission.id AS id')
            ->addSelect('join_inventoryMission.id AS missionId')
            ->addSelect('join_location.label AS location')
            ->addSelect('inventory_location_mission.done AS done')

            ->leftJoin('inventory_location_mission.inventoryMission', 'join_inventoryMission')
            ->leftJoin('inventory_location_mission.location', 'join_location');

        //if le champs done est à true
        $queryBuilder
            ->addSelect('join_zone.name AS zone')
            ->addSelect('join_referenceArticle.reference AS reference')
            ->addSelect('join_inventoryLocationMissionReferenceArticles.scannedAt AS scanDate')
            ->addSelect('join_user.username AS operator')
            ->addSelect('join_inventoryLocationMissionReferenceArticles.percentage AS percentage')

            ->leftJoin('inventory_location_mission.inventoryLocationMissionReferenceArticles', 'join_inventoryLocationMissionReferenceArticles')
            ->leftJoin('join_location.zone', 'join_zone')
            ->leftJoin('join_inventoryLocationMissionReferenceArticles.referenceArticle', 'join_referenceArticle')
            ->leftJoin('join_inventoryLocationMissionReferenceArticles.operator', 'join_user');
        //endif

        $queryBuilder
            ->andWhere('join_inventoryMission.id = :mission')
            ->addOrderBy('missionId')
            ->setParameter('mission', $mission->getId());

        if (!empty($search)) {
            $queryBuilder
                ->andWhere($exprBuilder->orX(
                    'join_zone.name LIKE :search',
                    'join_location.label LIKE :search',
                    'join_referenceArticle.reference LIKE :search',
                    'join_user.username LIKE :search',
                ))
                ->setParameter('search', "%$search%");
        }

        $queryResult = $queryBuilder->getQuery()->getResult();

        $result = Stream::from($queryResult)
            ->filterMap(fn(array $line) => ( [
                    "id" => $line["id"],
                    "missionId" => $line["missionId"],
                    "zone" => $line["zone"] ?? null,
                    "location" => $line["location"] ?? null,
                    "reference" => $line["reference"] ?? null,
                    "scanDate" => $line["scanDate"]?->format("d/m/Y H:i") ?? null,
                    "operator" => $line["operator"] ?? null,
                    "percentage" => $line["percentage"] . "%",
                    ]
            ));

        $total = $result->count();

        return [
            "data" => $result->values(),
            "total" => $total ?? 0
        ];
    }

}
