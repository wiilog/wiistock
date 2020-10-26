<?php

namespace App\Repository;

use App\Entity\MouvementStock;
use App\Entity\TrackingMovement;
use App\Entity\Utilisateur;
use DateTime;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Exception;


/**
 * @method TrackingMovement|null find($id, $lockMode = null, $lockVersion = null)
 * @method TrackingMovement|null findOneBy(array $criteria, array $orderBy = null)
 * @method TrackingMovement[]    findAll()
 * @method TrackingMovement[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TrackingMovementRepository extends EntityRepository
{

    public const MOUVEMENT_TRACA_DEFAULT = 'tracking';
    public const MOUVEMENT_TRACA_STOCK = 'stock';

    private const DtToDbLabels = [
        'date' => 'datetime',
        'code' => 'code',
        'location' => 'emplacement',
        'type' => 'status',
        'reference' => 'reference',
        'label' => 'label',
        'operateur' => 'user',
        'quantity' => 'quantity'
    ];
    private const FIELD_ENTITY_NAME = [
        "label" => "libelle",
        "warningThreshold" => "limitWarning",
        "securityThreshold" => "limitSecurity",
        "emergency" => "isUrgent",
        "availableQuantity" => "quantiteDisponible",
        "stockQuantity" => "quantiteStock",
        "location" => "emplacement",
        "quantityType" => "typeQuantite",
        "lastInventory" => "dateLastInventory",
        "mobileSync" => "needsMobileSync",
        "supplier" => "fournisseur",
        "unitPrice" => "prixUnitaire",
        "comment" => "commentaire",
    ];

    /**
     * @param $uniqueId
     * @return TrackingMovement
     * @throws NonUniqueResultException
     */
    public function findOneByUniqueIdForMobile($uniqueId) {
        return $this->createQueryBuilder('tracking_movement')
            ->select('tracking_movement')
            ->where('tracking_movement.uniqueIdForMobile = :uniqueId')
            ->setParameter('uniqueId', $uniqueId)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return int|mixed|string
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    public function countAll()
    {
        $qb = $this->createQueryBuilder('tracking_movement');

        $qb->select('COUNT(tracking_movement)');

        return $qb
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @param DateTime $dateMin
     * @param DateTime $dateMax
     * @return TrackingMovement[]
     * @throws Exception
     */
    public function getByDates(DateTime $dateMin,
                               DateTime $dateMax)
    {
        $dateMax = $dateMax->format('Y-m-d H:i:s');
        $dateMin = $dateMin->format('Y-m-d H:i:s');

        $queryBuilder = $this->createQueryBuilder('tracking_movement')
            ->select('tracking_movement.id')
            ->addSelect('tracking_movement.datetime')
            ->addSelect('pack.code AS code')
            ->addSelect('tracking_movement.quantity')
            ->addSelect('join_location.label as locationLabel')
            ->addSelect('join_type.nom as typeName')
            ->addSelect('join_operator.username as operatorUsername')
            ->addSelect('tracking_movement.commentaire')
            ->addSelect('pack_arrival.numeroArrivage')
            ->addSelect('pack_arrival.numeroCommandeList AS numeroCommandeListArrivage')
            ->addSelect('pack_arrival.isUrgent')
            ->addSelect('join_reception.numeroReception')
            ->addSelect('join_reception.orderNumber AS orderNumber')
            ->addSelect('tracking_movement.freeFields')

            ->andWhere('tracking_movement.datetime BETWEEN :dateMin AND :dateMax')

            ->innerJoin('tracking_movement.pack', 'pack')
            ->leftJoin('tracking_movement.emplacement', 'join_location')
            ->leftJoin('tracking_movement.type', 'join_type')
            ->leftJoin('tracking_movement.operateur', 'join_operator')
            ->leftJoin('pack.arrivage', 'pack_arrival')
            ->leftJoin('tracking_movement.reception', 'join_reception')

            ->setParameters([
                'dateMin' => $dateMin,
                'dateMax' => $dateMax
            ]);

        return $queryBuilder
            ->getQuery()
            ->getResult();
    }

    /**
     * @param array|null $params
     * @param array|null $filters
     * @return array
     * @throws Exception
     */
    public function findByParamsAndFilters($params, $filters)
    {
        $qb = $this->createQueryBuilder('tracking_movement');

        $countTotal = $this->countAll();

        // filtres sup
        foreach ($filters as $filter) {
            switch ($filter['field']) {
                case 'statut':
                    $value = explode(',', $filter['value']);
                    $qb
                        ->join('tracking_movement.type', 'filter_type')
                        ->andWhere('filter_type.id in (:type)')
                        ->setParameter('type', $value);
                    break;
                case 'emplacement':
                    $emplacementValue = explode(':', $filter['value']);
                    $qb
                        ->join('tracking_movement.emplacement', 'filter_location')
                        ->andWhere('filter_location.label = :location')
                        ->setParameter('location', $emplacementValue[1] ?? $filter['value']);
                    break;
                case 'utilisateurs':
                    $value = explode(',', $filter['value']);
                    $qb
                        ->join('tracking_movement.operateur', 'filter_operator')
                        ->andWhere("filter_operator.id in (:userId)")
                        ->setParameter('userId', $value);
                    break;
                case 'dateMin':
                    $qb
                        ->andWhere('tracking_movement.datetime >= :dateMin')
                        ->setParameter('dateMin', $filter['value'] . " 00:00:00");
                    break;
                case 'dateMax':
                    $qb
                        ->andWhere('tracking_movement.datetime <= :dateMax')
                        ->setParameter('dateMax', $filter['value'] . " 23:59:59");
                    break;
                case 'colis':
                    $qb
                        ->leftJoin('tracking_movement.pack', 'filter_pack')
                        ->andWhere('filter_pack.code LIKE :filter_code')
                        ->setParameter('filter_code', '%' . $filter['value'] . '%');
                    break;
           }
        }

        //Filter search
        if (!empty($params)) {
            if (!empty($params->get('search'))) {
                $search = $params->get('search')['value'];
                if (!empty($search)) {
                    $qb
                        ->innerJoin('tracking_movement.pack', 'search_pack')
                        ->leftJoin('tracking_movement.emplacement', 'search_location')
                        ->leftJoin('tracking_movement.operateur', 'search_operator')
                        ->leftJoin('tracking_movement.type', 'search_type')
                        ->leftJoin('search_pack.referenceArticle', 'search_pack_referenceArticle')
                        ->leftJoin('search_pack.article', 'search_pack_article')
                        ->leftJoin('search_pack_article.articleFournisseur', 'search_pack_article_supplierItem')
                        ->leftJoin('search_pack_article_supplierItem.referenceArticle', 'search_pack_supplierItem_referenceArticle')
                        ->andWhere('(
                            search_pack.code LIKE :search_value OR
                            search_location.label LIKE :search_value OR
                            search_type.nom LIKE :search_value OR
                            search_pack_supplierItem_referenceArticle.reference LIKE :search_value OR
                            search_pack_article.label LIKE :search_value OR
                            search_pack_referenceArticle.reference LIKE :search_value OR
                            search_pack_referenceArticle.libelle LIKE :search_value OR
                            search_operator.username LIKE :search_value
						)')
                        ->setParameter('search_value', '%' . $search . '%');
                }
            }

            if (!empty($params->get('order'))) {
                $order = $params->get('order')[0]['dir'];
                if (!empty($order)) {
                    $column = self::DtToDbLabels[$params->get('columns')[$params->get('order')[0]['column']]['data']];

                    if ($column === 'emplacement') {
                        $qb
                            ->leftJoin('tracking_movement.emplacement', 'order_location')
                            ->orderBy('order_location.label', $order);
                    } else if ($column === 'status') {
                        $qb
                            ->leftJoin('tracking_movement.type', 'order_type')
                            ->orderBy('order_type.nom', $order);
                    } else if ($column === 'reference') {
                        $qb
                            ->innerJoin('tracking_movement.pack', 'order_pack')
                            ->leftJoin('order_pack.referenceArticle', 'order_pack_referenceArticle')
                            ->leftJoin('order_pack.article', 'order_pack_article')
                            ->leftJoin('order_pack_article.articleFournisseur', 'order_pack_article_articleFournisseur')
                            ->leftJoin('order_pack_article_articleFournisseur.referenceArticle', 'order_pack_article_articleFournisseur_referenceArticle')
                            ->orderBy('order_pack_referenceArticle.reference', $order)
                            ->addOrderBy('order_pack_article_articleFournisseur_referenceArticle.reference', $order);
                    } else if ($column === 'label') {
                        $qb
                            ->innerJoin('tracking_movement.pack', 'order_pack')
                            ->leftJoin('order_pack.referenceArticle', 'order_pack_referenceArticle')
                            ->leftJoin('order_pack.article', 'order_pack_article')
                            ->orderBy('order_pack_referenceArticle.libelle', $order)
                            ->addOrderBy('order_pack_article.label', $order);
                    } else if ($column === 'user') {
                        $qb
                            ->leftJoin('tracking_movement.operateur', 'order_operator')
                            ->orderBy('order_operator.username', $order);
                    }  else if ($column === 'code') {
                        $qb
                            ->leftJoin('tracking_movement.pack', 'order_pack')
                            ->orderBy('order_pack.code', $order);
                    } else {
                        $qb
                            ->orderBy('tracking_movement.' . $column, $order);
                    }

                    $orderId = ($column === 'datetime')
                        ? $order
                        : 'DESC';
                    $qb->addOrderBy('tracking_movement.id', $orderId);
                }
            }
        }

        // compte éléments filtrés
        $qb
            ->select('count(tracking_movement)');
        // compte éléments filtrés
        $countFiltered = $qb->getQuery()->getSingleScalarResult();
        $qb
            ->select('tracking_movement');

        if ($params) {
            if (!empty($params->get('start'))) $qb->setFirstResult($params->get('start'));
            if (!empty($params->get('length'))) $qb->setMaxResults($params->get('length'));
        }

        $query = $qb->getQuery();

        return [
            'data' => $query ? $query->getResult() : null,
            'count' => $countFiltered,
            'total' => $countTotal
        ];
    }

    /**
     * @param Utilisateur $operator
     * @param string $type self::MOUVEMENT_TRACA_STOCK | self::MOUVEMENT_TRACA_DEFAULT
     * @param array $filterDemandeCollecteIds
     * @return TrackingMovement[]
     */
    public function getTakingByOperatorAndNotDeposed(Utilisateur $operator,
                                                     string $type,
                                                     array $filterDemandeCollecteIds = []) {
        $queryBuilder = $this->createQueryBuilder('tracking_movement')
            ->select('join_pack.code AS ref_article')
            ->addSelect('join_trackingType.nom AS type')
            ->addSelect('tracking_movement.quantity AS quantity')
            ->addSelect('tracking_movement.freeFields')
            ->addSelect('join_operator.username AS operateur')
            ->addSelect('join_location.label AS ref_emplacement')
            ->addSelect('tracking_movement.uniqueIdForMobile AS date')
            ->addSelect('join_pack_nature.id AS nature_id')
            ->addSelect('(CASE WHEN tracking_movement.finished = 1 THEN 1 ELSE 0 END) AS finished')
            ->addSelect('(CASE WHEN tracking_movement.mouvementStock IS NOT NULL THEN 1 ELSE 0 END) AS fromStock');

        $typeCondition = ($type === self::MOUVEMENT_TRACA_STOCK)
            ? 'join_stockMovement.id IS NOT NULL'
            : 'join_stockMovement.id IS NULL'; // MOUVEMENT_TRACA_DEFAULT
        if ($type === self::MOUVEMENT_TRACA_STOCK) {
            $queryBuilder->addSelect('join_stockMovement.quantity');
        }

        $queryBuilder
            ->join('tracking_movement.type', 'join_trackingType')
            ->join('tracking_movement.operateur', 'join_operator')
            ->join('tracking_movement.emplacement', 'join_location')
            ->leftJoin('tracking_movement.pack', 'join_pack')
            ->leftJoin('join_pack.nature', 'join_pack_nature')
            ->leftJoin('tracking_movement.mouvementStock', 'join_stockMovement')
            ->where('join_operator = :operator')
            ->andWhere('join_trackingType.nom LIKE :priseType')
            ->andWhere('tracking_movement.finished = :finished')
            ->andWhere($typeCondition)
            ->setParameter('operator', $operator)
            ->setParameter('priseType', TrackingMovement::TYPE_PRISE)
            ->setParameter('finished', false);

        if (!empty($filterDemandeCollecteIds)) {
            $queryBuilder
                ->join('join_stockMovement.collecteOrder', 'join_stockMovement_collectOrder')
                ->andWhere('join_stockMovement_collectOrder.id IN (:collecteOrderId)')
                ->setParameter('collecteOrderId', $filterDemandeCollecteIds, Connection::PARAM_STR_ARRAY);
        }

        return $queryBuilder
            ->getQuery()
            ->execute();
    }

    /**
     * @param $locationId
     * @return int|mixed|string
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    public function countByEmplacement($locationId)
    {
        $qb = $this->createQueryBuilder('tracking_movement');

        $qb
            ->select('COUNT(tracking_movement)')
            ->join('tracking_movement.emplacement', 'join_location')
            ->where('join_location.id = :locationId')
            ->setParameter('locationId', $locationId);

        return $qb
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @param MouvementStock $stockMovement
     * @return int
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    public function countByMouvementStock($stockMovement)
    {
        $qb = $this->createQueryBuilder('tracking_movement');

        $qb
            ->select('COUNT(tracking_movement)')
            ->where('tracking_movement.mouvementStock = :stockMovement')
            ->setParameter('stockMovement', $stockMovement);

        return $qb
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findLastTakingNotFinished(string $code) {
        return $this->createQueryBuilder('tracking_movement')
            ->join('tracking_movement.pack', 'join_pack')
            ->join('tracking_movement.type', 'join_type')
            ->where('join_pack.code = :code')
            ->andWhere('join_type.code = :takingCode')
            ->andWhere('tracking_movement.finished = false')
            ->orderBy('tracking_movement.datetime', 'DESC')
            ->setParameter('takingCode', TrackingMovement::TYPE_PRISE)
            ->setParameter('code', $code)
            ->getQuery()
            ->getResult();
    }
}
