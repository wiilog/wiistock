<?php

namespace App\Repository;

use App\Entity\MouvementStock;
use App\Entity\MouvementTraca;
use App\Entity\Utilisateur;
use DateTime;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Exception;


/**
 * @method MouvementTraca|null find($id, $lockMode = null, $lockVersion = null)
 * @method MouvementTraca|null findOneBy(array $criteria, array $orderBy = null)
 * @method MouvementTraca[]    findAll()
 * @method MouvementTraca[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class MouvementTracaRepository extends EntityRepository
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

    /**
     * @param $uniqueId
     * @return MouvementTraca
     * @throws NonUniqueResultException
     */
    public function findOneByUniqueIdForMobile($uniqueId)
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
        /** @lang DQL */
            'SELECT mvt
                FROM App\Entity\MouvementTraca mvt
                WHERE mvt.uniqueIdForMobile = :uniqueId'
        )->setParameter('uniqueId', $uniqueId);
        return $query->getOneOrNullResult();
    }

    /**
     * @return int|mixed|string
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    public function countAll()
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
        /** @lang DQL */
            "SELECT COUNT(m)
            FROM App\Entity\MouvementTraca m"
        );
        return $query->getSingleScalarResult();
    }

    /**
     * @param DateTime $dateMin
     * @param DateTime $dateMax
     * @return MouvementTraca[]
     * @throws Exception
     */
    public function getByDates(DateTime $dateMin,
                               DateTime $dateMax)
    {
        $dateMax = $dateMax->format('Y-m-d H:i:s');
        $dateMin = $dateMin->format('Y-m-d H:i:s');

        $queryBuilder = $this->createQueryBuilder('mouvementTraca')
            ->select('mouvementTraca.id')
            ->addSelect('mouvementTraca.datetime')
            ->addSelect('pack.code AS code')
            ->addSelect('mouvementTraca.quantity')
            ->addSelect('location.label as locationLabel')
            ->addSelect('type.nom as typeName')
            ->addSelect('operator.username as operatorUsername')
            ->addSelect('mouvementTraca.commentaire')
            ->addSelect('arrivage.numeroArrivage')
            ->addSelect('arrivage.numeroCommandeList AS numeroCommandeListArrivage')
            ->addSelect('arrivage2.isUrgent')
            ->addSelect('reception.numeroReception')
            ->addSelect('reception.reference AS referenceReception')
            ->addSelect('mouvementTraca.freeFields')

            ->andWhere('mouvementTraca.datetime BETWEEN :dateMin AND :dateMax')

            ->leftJoin('mouvementTraca.emplacement', 'location')
            ->leftJoin('mouvementTraca.type', 'type')
            ->leftJoin('mouvementTraca.operateur', 'operator')
            ->leftJoin('mouvementTraca.arrivage', 'arrivage')
            ->leftJoin('mouvementTraca.reception', 'reception')
            ->innerJoin('mouvementTraca.pack', 'pack')
            ->leftJoin('pack.arrivage', 'arrivage2')

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
        $em = $this->getEntityManager();
        $qb = $em->createQueryBuilder();

        $qb
            ->from('App\Entity\MouvementTraca', 'm');

        $countTotal = $this->countAll();

        // filtres sup
        foreach ($filters as $filter) {
            switch ($filter['field']) {
                case 'statut':
                    $value = explode(',', $filter['value']);
                    $qb
                        ->join('m.type', 's')
                        ->andWhere('s.id in (:statut)')
                        ->setParameter('statut', $value);
                    break;
                case 'emplacement':
                    $emplacementValue = explode(':', $filter['value']);
                    $qb
                        ->join('m.emplacement', 'e')
                        ->andWhere('e.label = :location')
                        ->setParameter('location', $emplacementValue[1] ?? $filter['value']);
                    break;
                case 'utilisateurs':
                    $value = explode(',', $filter['value']);
                    $qb
                        ->join('m.operateur', 'u')
                        ->andWhere("u.id in (:userId)")
                        ->setParameter('userId', $value);
                    break;
                case 'dateMin':
                    $qb
                        ->andWhere('m.datetime >= :dateMin')
                        ->setParameter('dateMin', $filter['value'] . " 00:00:00");
                    break;
                case 'dateMax':
                    $qb
                        ->andWhere('m.datetime <= :dateMax')
                        ->setParameter('dateMax', $filter['value'] . " 23:59:59");
                    break;
                case 'colis':
                    $qb
                        ->leftJoin('m.pack', 'filter_pack')
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
                        ->leftJoin('m.emplacement', 'e2')
                        ->leftJoin('m.operateur', 'u2')
                        ->leftJoin('m.type', 's2')
                        ->leftJoin('m.referenceArticle', 'mra1')
                        ->leftJoin('m.article', 'a1')
                        ->leftJoin('a1.articleFournisseur', 'af1')
                        ->leftJoin('af1.referenceArticle', 'afra1')
                        ->leftJoin('m.pack', 'search_pack')
                        ->andWhere('(
                            search_pack.code LIKE :search_value OR
                            e2.label LIKE :search_value OR
                            s2.nom LIKE :search_value OR
                            afra1.reference LIKE :search_value OR
                            a1.label LIKE :search_value OR
                            mra1.reference LIKE :search_value OR
                            mra1.libelle LIKE :search_value OR
                            u2.username LIKE :search_value
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
                            ->leftJoin('m.emplacement', 'e3')
                            ->orderBy('e3.label', $order);
                    } else if ($column === 'status') {
                        $qb
                            ->leftJoin('m.type', 's3')
                            ->orderBy('s3.nom', $order);
                    } else if ($column === 'reference') {
                        $qb
                            ->leftJoin('m.referenceArticle', 'mra')
                            ->leftJoin('m.article', 'a')
                            ->leftJoin('a.articleFournisseur', 'af')
                            ->leftJoin('af.referenceArticle', 'afra')
                            ->orderBy('mra.reference', $order)
                            ->addOrderBy('afra.reference', $order);
                    } else if ($column === 'label') {
                        $qb
                            ->leftJoin('m.referenceArticle', 'mra')
                            ->leftJoin('m.article', 'a')
                            ->orderBy('mra.libelle', $order)
                            ->addOrderBy('a.label', $order);
                    } else if ($column === 'user') {
                        $qb
                            ->leftJoin('m.operateur', 'u3')
                            ->orderBy('u3.username', $order);
                    }  else if ($column === 'code') {
                        $qb
                            ->leftJoin('m.pack', 'order_pack')
                            ->orderBy('order_pack.code', $order);
                    } else {
                        $qb
                            ->orderBy('m.' . $column, $order);
                    }

                    $orderId = ($column === 'datetime')
                        ? $order
                        : 'DESC';
                    $qb->addOrderBy('m.id', $orderId);
                }
            }
        }

        // compte éléments filtrés
        $qb
            ->select('count(m)');
        // compte éléments filtrés
        $countFiltered = $qb->getQuery()->getSingleScalarResult();
        $qb
            ->select('m');

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
     * @return MouvementTraca[]
     */
    public function getTakingByOperatorAndNotDeposed(Utilisateur $operator,
                                                     string $type,
                                                     array $filterDemandeCollecteIds = []) {
        $queryBuilder = $this->createQueryBuilder('mouvementTraca')
            ->select('pack.code AS ref_article')
            ->addSelect('mouvementTracaType.nom AS type')
            ->addSelect('mouvementTraca.quantity AS quantity')
            ->addSelect('mouvementTraca.freeFields')
            ->addSelect('operator.username AS operateur')
            ->addSelect('location.label AS ref_emplacement')
            ->addSelect('mouvementTraca.uniqueIdForMobile AS date')
            ->addSelect('nature.id AS nature_id')
            ->addSelect('(CASE WHEN mouvementTraca.finished = 1 THEN 1 ELSE 0 END) AS finished')
            ->addSelect('(CASE WHEN mouvementTraca.mouvementStock IS NOT NULL THEN 1 ELSE 0 END) AS fromStock');

        $typeCondition = ($type === self::MOUVEMENT_TRACA_STOCK)
            ? 'mouvementStock.id IS NOT NULL'
            : 'mouvementStock.id IS NULL'; // MOUVEMENT_TRACA_DEFAULT
        if ($type === self::MOUVEMENT_TRACA_STOCK) {
            $queryBuilder->addSelect('mouvementStock.quantity');
        }

        $queryBuilder
            ->join('mouvementTraca.type', 'mouvementTracaType')
            ->join('mouvementTraca.operateur', 'operator')
            ->join('mouvementTraca.emplacement', 'location')
            ->leftJoin('mouvementTraca.pack', 'pack')
            ->leftJoin('pack.nature', 'nature')
            ->leftJoin('mouvementTraca.mouvementStock', 'mouvementStock')
            ->where('operator = :operator')
            ->andWhere('mouvementTracaType.nom LIKE :priseType')
            ->andWhere('mouvementTraca.finished = :finished')
            ->andWhere($typeCondition)
            ->setParameter('operator', $operator)
            ->setParameter('priseType', MouvementTraca::TYPE_PRISE)
            ->setParameter('finished', false);

        if (!empty($filterDemandeCollecteIds)) {
            $queryBuilder
                ->join('mouvementStock.collecteOrder', 'collecteOrder')
                ->andWhere('collecteOrder.id IN (:collecteOrderId)')
                ->setParameter('collecteOrderId', $filterDemandeCollecteIds, Connection::PARAM_STR_ARRAY);
        }

        return $queryBuilder
            ->getQuery()
            ->execute();
    }

    /**
     * @param $emplacementId
     * @return int|mixed|string
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    public function countByEmplacement($emplacementId)
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
        /** @lang DQL */
            "SELECT COUNT(m)
            FROM App\Entity\MouvementTraca m
            JOIN m.emplacement e
            WHERE e.id = :emplacementId"
        )->setParameter('emplacementId', $emplacementId);
        return $query->getSingleScalarResult();
    }

    /**
     * @param MouvementStock $mouvementStock
     * @return int
     * @throws NonUniqueResultException
     * @throws NoResultException
     */
    public function countByMouvementStock($mouvementStock)
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
        /** @lang DQL */
            "SELECT COUNT(m)
            FROM App\Entity\MouvementTraca m
            WHERE m.mouvementStock = :mouvementStock"
        )->setParameter('mouvementStock', $mouvementStock);
        return $query->getSingleScalarResult();
    }

    public function findLastTakingNotFinished(string $code) {
        return $this->createQueryBuilder('movement')
            ->join('movement.pack', 'pack')
            ->join('movement.type', 'type')
            ->where('pack.code = :code')
            ->andWhere('type.code = :takingCode')
            ->andWhere('movement.finished = false')
            ->orderBy('movement.datetime', 'DESC')
            ->setParameter('takingCode', MouvementTraca::TYPE_PRISE)
            ->setParameter('code', $code)
            ->getQuery()
            ->getResult();
    }
}
