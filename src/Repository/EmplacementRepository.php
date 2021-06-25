<?php

namespace App\Repository;

use App\Entity\Emplacement;
use App\Entity\LocationGroup;
use Doctrine\ORM\EntityRepository;
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
        'pairing' => 'pairing',
    ];

    public function getForSelect(?string $term, $deliveryType = null, $collectType = null) {
        $query = $this->createQueryBuilder("location");

        if($deliveryType) {
            $query->leftJoin("location.allowedDeliveryTypes", "allowed_delivery_types")
                ->andWhere("allowed_delivery_types.id = :type")
                ->setParameter("type", $deliveryType);
        }

        if($collectType) {
            $query->leftJoin("location.allowedCollectTypes", "allowed_collect_types")
                ->andWhere("allowed_collect_types.id = :type")
                ->setParameter("type", $collectType);
        }

        return $query->select("location.id AS id, location.label AS text")
            ->andWhere("location.label LIKE :term")
            ->setParameter("term", "%$term%")
            ->getQuery()
            ->getArrayResult();
    }

    public function getLocationsArray()
    {
        return $this->createQueryBuilder('location')
            ->select('location.id')
            ->addSelect('location.label')
            ->where('location.isActive = true')
            ->getQuery()
            ->getResult();
    }

    public function countAll()
    {
        $qb = $this->createQueryBuilder('location');

        $qb->select('COUNT(location)');

        return $qb
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countByLabel($label, $emplacementId = null)
    {
        $qb = $this->createQueryBuilder('location');

        $qb->select('COUNT(location.label')
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

    public function findByParamsAndExcludeInactive($params = null, $excludeInactive = false)
    {
        $countTotal = $this->countAll();

        $em = $this->getEntityManager();
        $qb = $em
            ->createQueryBuilder()
            ->from('App\Entity\Emplacement', 'e');

        if ($excludeInactive) {
            $qb->where('e.isActive = 1');
        }

        if (!empty($params)) {
            if (!empty($params->get('search'))) {
                $search = $params->get('search')['value'];
                if (!empty($search)) {
                    $qb
                        ->andWhere('e.label LIKE :value OR e.description LIKE :value')
                        ->setParameter('value', '%' . $search . '%');
                }
            }
            if (!empty($params->get('order'))) {
                $order = $params->get('order')[0]['dir'];
                $field = self::DtToDbLabels[$params->get('columns')[$params->get('order')[0]['column']]['name']];
                if (!empty($order) && $field) {
                    if($field === 'pairing') {
                        $qb->leftJoin('e.pairings', 'order_pairings')
                            ->leftJoin('e.locationGroup', 'order_locationGroup')
                            ->leftJoin('order_locationGroup.pairings', 'order_locationGroupPairings')
                            ->orderBy('IFNULL(order_pairings.active, order_locationGroupPairings.active)', $order);
                    } else if(property_exists(Emplacement::class, $field)) {
                        $qb->orderBy("e.${field}", $order);
                    }
                }
            }
            $qb->select('count(e)');
            $countQuery = (int) $qb->getQuery()->getSingleScalarResult();
        }
        else {
            $countQuery = $countTotal;
        }

        $qb
            ->select('e');
        if ($params) {
            if (!empty($params->get('start'))) $qb->setFirstResult($params->get('start'));
            if (!empty($params->get('length'))) $qb->setMaxResults($params->get('length'));
        }
        $query = $qb->getQuery();
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
            ->where('pairings.location is null')
            ->andWhere("location.label LIKE :term")
            ->setParameter("term", "%$term%")
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
        $entityManager = $this->getEntityManager();
        $locationGroupRepository = $entityManager->getRepository(LocationGroup::class);
        $locationGroup = $location->getLocationGroup();

        $createQueryBuilder = function () {
            return $this->createQueryBuilder('location')
                ->select('pairing.id AS pairingId')
                ->addSelect('sensorWrapper.name AS name')
                ->addSelect('(CASE WHEN sensorWrapper.deleted = false AND pairing.active = true AND pairing.end IS NULL THEN 1 ELSE 0 END) AS active')
                ->addSelect('location.label AS entity')
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
            '/AS \w+_4/' => 'AS date',
            '/AS \w+_5/' => 'AS type',
            '/\?/' => $location->getId(),
        ];

        $startSQL = $startQueryBuilder->getQuery()->getSQL();
        $startSQL = StringHelper::multiplePregReplace($sqlAliases, $startSQL);

        $endSQL = $endQueryBuilder->getQuery()->getSQL();
        $endSQL = StringHelper::multiplePregReplace($sqlAliases, $endSQL);

        $res = "
            ($startSQL)
            UNION
            ($endSQL)
        ";

        if ($locationGroup) {
            $locationGroupSQL = $locationGroupRepository->createSensorPairingDataQueryUnion($locationGroup);
            $res .= "
                UNION
                $locationGroupSQL
            ";
        }

        return $res;
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
}
