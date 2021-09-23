<?php

namespace App\Repository;

use App\Entity\Emplacement;
use App\Entity\IOT\Sensor;
use App\Entity\LocationGroup;
use App\Entity\Pack;
use App\Helper\QueryCounter;
use Doctrine\ORM\EntityRepository;
use WiiCommon\Helper\StringHelper;

/**
 * @method LocationGroup|null find($id, $lockMode = null, $lockVersion = null)
 * @method LocationGroup|null findOneBy(array $criteria, array $orderBy = null)
 * @method LocationGroup[]    findAll()
 * @method LocationGroup[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class LocationGroupRepository extends EntityRepository
{

    public function findByParamsAndFilters($params)
    {
        $queryBuilder = $this->createQueryBuilder("location_group");

        $countTotal = QueryCounter::count($queryBuilder, "location_group");

        //Filter search
        if (!empty($params)) {
            if (!empty($params->get('search'))) {
                $search = $params->get('search')['value'];
                if (!empty($search)) {
                    $queryBuilder
                        ->andWhere($queryBuilder->expr()->orX(
                            "location_group.name LIKE :value",
                            "location_group.description LIKE :value",
                        ))
                        ->setParameter('value', '%' . $search . '%');
                }
            }

            if (!empty($params->get('order'))) {
                $order = $params->get('order')[0]['dir'];
                if (!empty($order)) {
                    $column = $params->get('columns')[$params->get('order')[0]['column']]['data'];
                    $queryBuilder->orderBy("location_group.$column", $order);
                }
            }
        }

        $countFiltered = QueryCounter::count($queryBuilder, "location_group");

        if ($params) {
            if (!empty($params->get('start'))) $queryBuilder->setFirstResult($params->get('start'));
            if (!empty($params->get('length'))) $queryBuilder->setMaxResults($params->get('length'));
        }

        $query = $queryBuilder->getQuery();
        return [
            'data' => $query ? $query->getResult() : null,
            'count' => $countFiltered,
            'total' => $countTotal
        ];
    }

    public function getWithNoAssociationForSelect($term)
    {
        return $this->createQueryBuilder('location_group')
            ->select("CONCAT('locationGroup:', location_group.id) AS id")
            ->addSelect('location_group.name AS text')
            ->leftJoin('location_group.pairings', 'pairings')
            ->where('pairings.locationGroup IS NULL OR pairings.active = 0')
            ->andWhere("location_group.name LIKE :term")
            ->setParameter("term", "%$term%")
            ->getQuery()
            ->getResult();
    }

    /**
     * @param LocationGroup $locationGroup
     * @return string
     */
    public function createSensorPairingDataQueryUnion(LocationGroup $locationGroup): string {
        $createQueryBuilder = function () {
            return $this->createQueryBuilder('locationGroup')
                ->select('pairing.id AS pairingId')
                ->addSelect('sensorWrapper.name AS name')
                ->addSelect('(CASE WHEN sensorWrapper.deleted = false AND pairing.active = true AND (pairing.end IS NULL OR pairing.end > NOW()) THEN 1 ELSE 0 END) AS active')
                ->addSelect('locationGroup.name AS entity')
                ->join('locationGroup.pairings', 'pairing')
                ->join('pairing.sensorWrapper', 'sensorWrapper')
                ->where('locationGroup = :locationGroup');
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
            '/AS \w+_4/' => 'AS date',
            '/AS \w+_5/' => 'AS type',
            '/\?/' => $locationGroup->getId()
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
    public function createLocationSensorPairingDataQueryUnion(Emplacement $location): string {
        $entityManager = $this->getEntityManager();
        $createQueryBuilder = function () use ($entityManager) {
            return $entityManager->createQueryBuilder()
                ->from(Emplacement::class, 'location')
                ->select('pairing.id AS pairingId')
                ->addSelect('sensorWrapper.name AS name')
                ->addSelect('(CASE WHEN sensorWrapper.deleted = false AND pairing.active = true AND (pairing.end IS NULL OR pairing.end > NOW()) THEN 1 ELSE 0 END) AS active')
                ->addSelect('locationGroup.name AS entity')
                ->addSelect("'" . Sensor::LOCATION_GROUP . "' AS entityType")
                ->addSelect('locationGroup.id AS entityId')
                ->join('location.sensorMessages', 'sensorMessage')
                ->join('sensorMessage.pairings', 'pairing')
                ->join('pairing.locationGroup', 'locationGroup')
                ->join('pairing.sensorWrapper', 'sensorWrapper')
                ->where('location = :location')
                ->andWhere('pairing.location IS NULL');
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
            '/AS \w+_4/' => 'AS entityId',
            '/AS \w+_5/' => 'AS entityType',
            '/AS \w+_6/' => 'AS date',
            '/AS \w+_7/' => 'AS type',
            '/\?/' => $location->getId(),
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
    public function createPackSensorPairingDataQueryUnion(Pack $pack): string {
        $entityManager = $this->getEntityManager();
        $createQueryBuilder = function () use ($entityManager) {
            return $entityManager->createQueryBuilder()
                ->from(Pack::class, 'pack')
                ->select('pairing.id AS pairingId')
                ->addSelect('sensorWrapper.name AS name')
                ->addSelect('(CASE WHEN sensorWrapper.deleted = false AND pairing.active = true AND (pairing.end IS NULL OR pairing.end > NOW()) THEN 1 ELSE 0 END) AS active')
                ->addSelect('locationGroup.name AS entity')
                ->addSelect("'" . Sensor::LOCATION_GROUP . "' AS entityType")
                ->addSelect("locationGroup.id AS entityId")
                ->join('pack.sensorMessages', 'sensorMessage')
                ->join('sensorMessage.pairings', 'pairing')
                ->join('pairing.locationGroup', 'locationGroup')
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

    public function getSensorPairingData(LocationGroup $locationGroup, int $start, int $count): array {
        $unionSQL = $this->createSensorPairingDataQueryUnion($locationGroup);

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

    public function countSensorPairingData(LocationGroup $locationGroup): int {
        $unionSQL = $this->createSensorPairingDataQueryUnion($locationGroup);

        $entityManager = $this->getEntityManager();
        $connection = $entityManager->getConnection();
        $unionQuery = $connection->executeQuery("
            SELECT COUNT(*) AS count
            FROM ($unionSQL) AS pairing
        ");
        $res = $unionQuery->fetchAllAssociative();
        return $res[0]['count'] ?? 0;
    }

}
