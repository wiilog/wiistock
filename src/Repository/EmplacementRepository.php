<?php

namespace App\Repository;

use App\Entity\Article;
use App\Entity\Emplacement;
use App\Entity\Inventory\InventoryMission;
use App\Entity\IOT\Sensor;
use App\Entity\LocationGroup;
use App\Entity\Pack;
use App\Entity\Transport\TransportRound;
use App\Entity\Utilisateur;
use App\Entity\Zone;
use DateTime;
use Doctrine\ORM\EntityRepository;
use Symfony\Component\HttpFoundation\InputBag;
use WiiCommon\Helper\StringHelper;

/**
 * @method Emplacement|null find($id, $lockMode = null, $lockVersion = null)
 * @method Emplacement|null findOneBy(array $criteria, array $orderBy = null)
 * @method Emplacement[]    findAll()
 * @method Emplacement[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class EmplacementRepository extends EntityRepository
{

    private const DtToDbLabels = [
        'name' => 'label',
        'deliveryPoint' => 'isDeliveryPoint',
        'ongoingVisibleOnMobile' => 'isOngoingVisibleOnMobile',
        'maxDelay' => 'dateMaxTime',
        'active' => 'isActive',
    ];

    public function getForSelect(?string $term, array $options = []) {

        $idPrefix = $options['idPrefix'] ?? '';
        $deliveryType = $options['deliveryType'] ?? '';
        $collectType = $options['collectType'] ?? '';

        $query = $this->createQueryBuilder("location")
            ->groupBy('location');

        if($deliveryType && $deliveryType !== 'all') {
            $types = is_array($deliveryType) ? $deliveryType : [$deliveryType];
            $query->leftJoin("location.allowedDeliveryTypes", "allowed_delivery_types")
                ->andWhere("allowed_delivery_types.id IN (:types)")
                ->setParameter("types", $types);
        }

        if($collectType) {
            $query->leftJoin("location.allowedCollectTypes", "allowed_collect_types")
                ->andWhere("allowed_collect_types.id = :type")
                ->setParameter("type", $collectType);
        }

        return $query->select("CONCAT('$idPrefix', location.id) AS id, location.label AS text")
            ->andWhere("location.label LIKE :term")
            ->andWhere("location.isActive = true")
            ->leftJoin("location.zone", "location_zone")
            ->andWhere("location_zone.active = true")
            ->setParameter("term", "%$term%")
            ->getQuery()
            ->getArrayResult();
    }

    public function getLocationsArray()
    {
        return $this->createQueryBuilder('location')
            ->select('location.id')
            ->addSelect('location.label')
            ->addSelect("GROUP_CONCAT(join_temperature_ranges.value SEPARATOR ';') AS temperature_ranges")
            ->where('location.isActive = true')
            ->leftJoin('location.temperatureRanges', 'join_temperature_ranges')
            ->groupBy('location.id')
            ->addGroupBy('location.label')
            ->getQuery()
            ->getResult();
    }

    public function countAll()
    {
        return $this->createQueryBuilder('location')
            ->select('COUNT(location)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countByLabel($label, $emplacementId = null)
    {
        $qb = $this->createQueryBuilder('location');

        $qb->select('COUNT(location.label)')
            ->where('location.label = :label');

		if ($emplacementId) {
            $qb->andWhere('location.id != :id');
		}

        $qb->setParameter('label', $label);

		if ($emplacementId) {
            $qb->setParameter('id', $emplacementId);
		}

        return $qb
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function getIdAndLabelActiveBySearch($search)
    {
        $qb = $this->createQueryBuilder('location');

        $qb->select('location.id AS id')
            ->addSelect('location.label AS text')
            ->where('location.label LIKE :search')
            ->andWhere('location.isActive = 1')
            ->orderBy('location.label', 'ASC')
            ->setParameter('search', '%' . str_replace('_', '\_', $search) . '%');

        return $qb
            ->getQuery()
            ->execute();
    }

    public function getLocationsByType($type, $search, $restrictResults) {
        $qb = $this->createQueryBuilder('location');

        $qb->select('location.id AS id')
            ->addSelect('location.label AS text')
            ->andWhere('location.label LIKE :search')
            ->setParameter('search', '%' . str_replace('_', '\_', $search) . '%');

        if ($type) {
            $qb
                ->andWhere('(:type MEMBER OF location.allowedDeliveryTypes) OR (:type MEMBER OF location.allowedCollectTypes)')
                ->setParameter('type', $type);
        }

        if ($restrictResults) {
            $qb->setMaxResults(50);
        }

        return $qb->getQuery()->getResult();
    }

    public function findByParamsAndExcludeInactive(InputBag $params = null, $excludeInactive = false)
    {
        $countTotal = $this->countAll();

        $queryBuilder = $this->createQueryBuilder('location');
        $exprBuilder = $queryBuilder->expr();

        if ($excludeInactive) {
            $queryBuilder->andWhere('location.isActive = 1');
        }

        if (!empty($params)) {
            if (!empty($params->all('search'))) {
                $search = $params->all('search')['value'];
                if (!empty($search)) {
                    $queryBuilder
                        ->leftJoin('location.signatories', 'search_signatory')
                        ->leftJoin('location.zone', 'search_zone')
                        ->andWhere($exprBuilder->orX(
                            'location.label LIKE :value',
                            'location.description LIKE :value',
                            'location.email LIKE :value',
                            'search_signatory.username LIKE :value',
                            'search_zone.name LIKE :value',
                        ))
                        ->setParameter('value', '%' . $search . '%');
                }
            }
            if (!empty($params->all('order'))) {
                $order = $params->all('order')[0]['dir'];
                $columnName = $params->all('columns')[$params->all('order')[0]['column']]['name'];
                $field = self::DtToDbLabels[$columnName] ?? $columnName;
                if (!empty($order) && $field) {
                    switch ($field) {
                        case 'pairing':
                            $queryBuilder
                                ->leftJoin('location.pairings', 'order_pairings')
                                ->leftJoin('location.locationGroup', 'order_locationGroup')
                                ->leftJoin('order_locationGroup.pairings', 'order_locationGroupPairings')
                                ->addOrderBy('IFNULL(order_pairings.active, order_locationGroupPairings.active)', $order);
                            break;
                        default:
                            if(property_exists(Emplacement::class, $field)) {
                                $queryBuilder->addOrderBy("location.${field}", $order);
                            }
                            break;
                    }
                }
            }
            $queryBuilder->select('count(location)');
            $countQuery = (int) $queryBuilder->getQuery()->getSingleScalarResult();
        }
        else {
            $countQuery = $countTotal;
        }

        $queryBuilder
            ->select('location');

        if ($params->getInt('start')) $queryBuilder->setFirstResult($params->getInt('start'));
        if ($params->getInt('length')) $queryBuilder->setMaxResults($params->getInt('length'));

        $query = $queryBuilder->getQuery();
        return [
            'data' => $query ? $query->getResult() : null,
            'allEmplacementDataTable' => !empty($params) ? $query->getResult() : null,
            'count' => $countQuery,
            'total' => $countTotal
        ];
    }

    public function findWhereArticleIs()
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
            "
            SELECT e.id, e.label, (
                SELECT COUNT(m)
                FROM App\Entity\TrackingMovement AS m
                JOIN m.emplacement e_other
                JOIN m.type t
                WHERE e_other.label = e.label AND t.nom LIKE 'depose'
            ) AS nb
            FROM App\Entity\Emplacement AS e
            WHERE e.dateMaxTime IS NOT NULL AND e.dateMaxTime != ''
            ORDER BY nb DESC"
        );
        return $query->execute();
    }

    public function getWithNoAssociationForSelect($term) {
        return $this->createQueryBuilder('location')
            ->select("CONCAT('location:', location.id) AS id")
            ->addSelect('location.label AS text')
            ->leftJoin('location.pairings', 'pairings')
            ->where('pairings.location is null OR pairings.active = 0')
            ->andWhere("location.label LIKE :term")
            ->setParameter("term", "%$term%")
            ->getQuery()
            ->getResult();
    }

    public function findByMissionAndZone(array $zones, InventoryMission $mission) {
        return $this->createQueryBuilder('location')
            ->join('location.inventoryLocationMissions', 'inventory_location_missions')
            ->andWhere('location.zone IN (:zones)')
            ->andWhere('inventory_location_missions.inventoryMission = :mission')
            ->setParameters([
                'zones' => $zones,
                'mission' => $mission,
            ])
            ->getQuery()
            ->getResult();
    }

    public function findWithActivePairing(){
        $qb = $this->createQueryBuilder('location');
        $qb
            ->leftJoin('location.pairings', 'pairings')
            ->where('pairings.active = 1');

        return $qb
            ->getQuery()
            ->getResult();
    }

    private function createSensorPairingDataQueryUnion(Emplacement $location): string {
        $createQueryBuilder = function () {
            return $this->createQueryBuilder('location')
                ->select('pairing.id AS pairingId')
                ->addSelect('sensorWrapper.name AS name')
                ->addSelect('(CASE WHEN sensorWrapper.deleted = false AND pairing.active = true AND (pairing.end IS NULL OR pairing.end > NOW()) THEN 1 ELSE 0 END) AS active')
                ->addSelect('location.label AS entity')
                ->addSelect("'" . Sensor::LOCATION . "' AS entityType")
                ->addSelect('location.id AS entityId')
                ->join('location.pairings', 'pairing')
                ->join('pairing.sensorWrapper', 'sensorWrapper')
                ->where('location = :location');
        };

        $startQueryBuilder = $createQueryBuilder();
        $startQueryBuilder
            ->addSelect("pairing.start AS date")
            ->addSelect("'start' AS type")
            ->andWhere('pairing.start IS NOT NULL');

        $endQueryBuilder = $createQueryBuilder();
        $endQueryBuilder
            ->addSelect("pairing.end AS date")
            ->addSelect("'end' AS type")
            ->andWhere('pairing.end IS NOT NULL');

        $sqlAliases = [
            '/AS \w+_0/' => 'AS pairingId',
            '/AS \w+_1/' => 'AS name',
            '/AS \w+_2/' => 'AS active',
            '/AS \w+_3/' => 'AS entity',
            '/AS \w+_4/' => 'AS entityType',
            '/AS \w+_5/' => 'AS entityId',
            '/AS \w+_6/' => 'AS date',
            '/AS \w+_7/' => 'AS type',
            '/\?/' => $location->getId(),
        ];

        $startSQL = $startQueryBuilder->getQuery()->getSQL();
        $startSQL = StringHelper::multiplePregReplace($sqlAliases, $startSQL);

        $endSQL = $endQueryBuilder->getQuery()->getSQL();
        $endSQL = StringHelper::multiplePregReplace($sqlAliases, $endSQL);

        $entityManager = $this->getEntityManager();
        $locationGroupRepository = $entityManager->getRepository(LocationGroup::class);
        $locationGroupSQL = $locationGroupRepository->createLocationSensorPairingDataQueryUnion($location);

        return "
            ($startSQL)
            UNION
            ($endSQL)
            UNION
            $locationGroupSQL
        ";
    }

    public function getSensorPairingData(Emplacement $location, int $start, int $count): array {
        $unionSQL = $this->createSensorPairingDataQueryUnion($location);

        $entityManager = $this->getEntityManager();
        $connection = $entityManager->getConnection();
        /** @noinspection SqlResolve */
        return $connection
            ->executeQuery("
                SELECT *
                FROM ($unionSQL) AS pairing
                ORDER BY `date` DESC
                LIMIT $count OFFSET $start
            ")
            ->fetchAllAssociative();
    }

    public function countSensorPairingData(Emplacement $location): int {
        $unionSQL = $this->createSensorPairingDataQueryUnion($location);

        $entityManager = $this->getEntityManager();
        $connection = $entityManager->getConnection();
        $unionQuery = $connection->executeQuery("
            SELECT COUNT(*) AS count
            FROM ($unionSQL) AS pairing
        ");
        $res = $unionQuery->fetchAllAssociative();
        return $res[0]['count'] ?? 0;
    }

    /**
     * @param LocationGroup $locationGroup
     * @return string
     */
    public function createPackSensorPairingDataQueryUnion(Pack $pack): string {
        $entityManager = $this->getEntityManager();
        $createQueryBuilder = function () use ($entityManager) {
            return $entityManager->createQueryBuilder()
                ->from(Pack::class, 'pack')
                ->select('pairing.id AS pairingId')
                ->addSelect('sensorWrapper.name AS name')
                ->addSelect('(CASE WHEN sensorWrapper.deleted = false AND pairing.active = true AND (pairing.end IS NULL OR pairing.end > NOW()) THEN 1 ELSE 0 END) AS active')
                ->addSelect('location.label AS entity')
                ->addSelect("'" . Sensor::LOCATION . "' AS entityType")
                ->addSelect('location.id AS entityId')
                ->join('pack.sensorMessages', 'sensorMessage')
                ->join('sensorMessage.pairings', 'pairing')
                ->join('pairing.location', 'location')
                ->join('pairing.sensorWrapper', 'sensorWrapper')
                ->where('pack = :pack')
                ->andWhere('pairing.pack IS NULL');
        };

        $startQueryBuilder = $createQueryBuilder();
        $startQueryBuilder
            ->addSelect("pairing.start AS date")
            ->addSelect("'start' AS type")
            ->andWhere('pairing.start IS NOT NULL');

        $endQueryBuilder = $createQueryBuilder();
        $endQueryBuilder
            ->addSelect("pairing.end AS date")
            ->addSelect("'end' AS type")
            ->andWhere('pairing.end IS NOT NULL');

        $sqlAliases = [
            '/AS \w+_0/' => 'AS pairingId',
            '/AS \w+_1/' => 'AS name',
            '/AS \w+_2/' => 'AS active',
            '/AS \w+_3/' => 'AS entity',
            '/AS \w+_4/' => 'AS entityType',
            '/AS \w+_5/' => 'AS entityId',
            '/AS \w+_6/' => 'AS date',
            '/AS \w+_7/' => 'AS type',
            '/\?/' => $pack->getId(),
        ];

        $startSQL = $startQueryBuilder->getQuery()->getSQL();
        $startSQL = StringHelper::multiplePregReplace($sqlAliases, $startSQL);

        $endSQL = $endQueryBuilder->getQuery()->getSQL();
        $endSQL = StringHelper::multiplePregReplace($sqlAliases, $endSQL);

        return "
            ($startSQL)
            UNION
            ($endSQL)
        ";
    }

    /**
     * @param LocationGroup $locationGroup
     * @return string
     */
    public function createArticleSensorPairingDataQueryUnion(Article $article): string {
        $entityManager = $this->getEntityManager();
        $createQueryBuilder = function () use ($entityManager) {
            return $entityManager->createQueryBuilder()
                ->from(Article::class, 'article')
                ->select('pairing.id AS pairingId')
                ->addSelect('sensorWrapper.name AS name')
                ->addSelect('(CASE WHEN sensorWrapper.deleted = false AND pairing.active = true AND (pairing.end IS NULL OR pairing.end > NOW()) THEN 1 ELSE 0 END) AS active')
                ->addSelect('location.label AS entity')
                ->addSelect("'" . Sensor::LOCATION . "' AS entityType")
                ->addSelect('location.id AS entityId')
                ->join('article.sensorMessages', 'sensorMessage')
                ->join('sensorMessage.pairings', 'pairing')
                ->join('pairing.location', 'location')
                ->join('pairing.sensorWrapper', 'sensorWrapper')
                ->where('article = :article')
                ->andWhere('pairing.article IS NULL');
        };

        $startQueryBuilder = $createQueryBuilder();
        $startQueryBuilder
            ->addSelect("pairing.start AS date")
            ->addSelect("'start' AS type")
            ->andWhere('pairing.start IS NOT NULL');

        $endQueryBuilder = $createQueryBuilder();
        $endQueryBuilder
            ->addSelect("pairing.end AS date")
            ->addSelect("'end' AS type")
            ->andWhere('pairing.end IS NOT NULL');

        $sqlAliases = [
            '/AS \w+_0/' => 'AS pairingId',
            '/AS \w+_1/' => 'AS name',
            '/AS \w+_2/' => 'AS active',
            '/AS \w+_3/' => 'AS entity',
            '/AS \w+_4/' => 'AS entityType',
            '/AS \w+_5/' => 'AS entityId',
            '/AS \w+_6/' => 'AS date',
            '/AS \w+_7/' => 'AS type',
            '/\?/' => $article->getId(),
        ];

        $startSQL = $startQueryBuilder->getQuery()->getSQL();
        $startSQL = StringHelper::multiplePregReplace($sqlAliases, $startSQL);

        $endSQL = $endQueryBuilder->getQuery()->getSQL();
        $endSQL = StringHelper::multiplePregReplace($sqlAliases, $endSQL);

        return "
            ($startSQL)
            UNION
            ($endSQL)
        ";
    }

    public function countRound(Emplacement|int $location): int {
        return $this->getEntityManager()
            ->createQueryBuilder()
            ->from(TransportRound::class, 'round')
            ->select('COUNT(round)')
            ->andWhere(':location MEMBER OF round.locations')
            ->setParameter('location', $location)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return TransportRound[]
     */
    public function findOngoingRounds(Emplacement $location): array {
        $queryBuilder = $this->getEntityManager()->createQueryBuilder();
        return $queryBuilder
            ->from(TransportRound::class, 'round')
            ->select('round')
            ->andWhere(':location MEMBER OF round.locations')
            ->andWhere('round.beganAt < :now')
            ->andWhere('round.endedAt IS NULL')
            ->setParameter('location', $location)
            ->setParameter('now', new DateTime())
            ->getQuery()
            ->getResult();
    }

    public function countLocationByUser(Utilisateur $user): int
    {
        return $this->createQueryBuilder('location')
            ->select('COUNT(location)')
            ->leftJoin('location.signatories', 'signatory')
            ->andWhere('signatory.id = :user')
            ->setParameter('user', $user->getId())
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countByUser(Utilisateur $user) {
        $queryBuilder = $this->getEntityManager()->createQueryBuilder();
        return $queryBuilder
            ->from(Emplacement::class, 'location')
            ->select('COUNT(location)')
            ->andWhere('user = :user')
            ->join('location.signatories', 'user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function isLocationInZoneInventoryMissionRule(Zone $zone): bool {
        return $this->createQueryBuilder('location')
            ->select('COUNT(location)')
            ->andWhere('location.zone = :zone')
            ->andWhere('location.inventoryMissionRules IS NOT EMPTY')
            ->setParameter('zone', $zone)
            ->getQuery()
            ->getSingleScalarResult() > 0;
    }

    public function isLocationInNotDoneInventoryMission(Zone $zone): bool {
        return $this->createQueryBuilder('location')
            ->select('COUNT(location)')
            ->andWhere('location.zone = :zone')
            ->andWhere('location.inventoryLocationMissions IS NOT EMPTY')
            ->andWhere('inventoryMission.done = false OR inventoryMission.done IS NULL')
            ->innerJoin('location.inventoryLocationMissions', 'inventoryLocationMission')
            ->innerJoin('inventoryLocationMission.inventoryMission', 'inventoryMission')
            ->setParameter('zone', $zone)
            ->getQuery()
            ->getSingleScalarResult() > 0;
    }
}
