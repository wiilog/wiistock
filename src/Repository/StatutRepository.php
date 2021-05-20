<?php

namespace App\Repository;

use App\Entity\CategorieStatut;
use App\Entity\Statut;
use App\Entity\Type;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityRepository;

/**
 * @method Statut|null find($id, $lockMode = null, $lockVersion = null)
 * @method Statut|null findOneBy(array $criteria, array $orderBy = null)
 * @method Statut[]    findAll()
 * @method Statut[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class StatutRepository extends EntityRepository {

    private const DtToDbLabels = [
        'category' => 'categorie',
        'label' => 'nom',
        'comment' => 'comment',
        'defaultStatus' => 'defaultForCategory',
        'state' => 'state',
        'notifToDeclarant' => 'sendNotifToDeclarant',
        'order' => 'displayOrder'
    ];

    public function countDrafts($category, $type, $current = null) {
        $qb = $this->createQueryBuilder("s")
            ->select("COUNT(s)")
            ->where("s.categorie = :category")
            ->andWhere("s.state = :draftId")
            ->setParameter("category", $category)
            ->setParameter('draftId', Statut::DRAFT);

        if($type) {
            $qb->andWhere("s.type = :type")
                ->setParameter("type", $type);
        } else {
            $qb->andWhere("s.type IS NULL");
        }

        if ($current) {
            $qb->andWhere("s.id != :current")
                ->setParameter("current", $current);
        }

        return $qb->getQuery()->getSingleScalarResult();
    }

    public function countDisputes($category, $type, $current = null) {
        $qb = $this->createQueryBuilder("s")
            ->select("COUNT(s)")
            ->where("s.categorie = :category")
            ->andWhere("s.type = :type")
            ->andWhere("s.state = :dispute")
            ->setParameter("category", $category)
            ->setParameter("type", $type)
            ->setParameter('dispute', Statut::DISPUTE);

        if ($current) {
            $qb->andWhere("s.id != :current")
                ->setParameter("current", $current);
        }

        return $qb->getQuery()->getSingleScalarResult();
    }

    public function countDefaults($category, $type, $current = null) {
        $qb = $this->createQueryBuilder("s")
            ->select("COUNT(s)")
            ->where("s.categorie = :category")
            ->andWhere("s.defaultForCategory = 1")
            ->setParameter("category", $category);

        if($type) {
            $qb->andWhere("s.type = :type")
                ->setParameter("type", $type);
        } else {
            $qb->andWhere("s.type IS NULL");
        }

        if ($current) {
            $qb->andWhere("s.id != :current")
                ->setParameter("current", $current);
        }

        return $qb->getQuery()->getSingleScalarResult();
    }

    public function findByCategorieName($categorieName,
                                        $orderByField = false) {
        $statutEntity = $this->getEntityManager()->getClassMetadata(Statut::class);

        $queryBuilder = $this->createQueryBuilder('status')
            ->join('status.categorie', 'categorie')
            ->andWhere('categorie.nom = :categorieName');

        if ($orderByField && $statutEntity->hasField($orderByField)) {
            $queryBuilder->orderBy('status.' . $orderByField, 'ASC');
        }

        $queryBuilder
            ->setParameter("categorieName", $categorieName);

        return $queryBuilder
            ->getQuery()
            ->execute();
    }

    public function getIdDefaultsByCategoryName(string $categoryName): array {
        $queryBuilder = $this->createQueryBuilder('status')
            ->addSelect('type.id AS typeId')
            ->join('status.categorie', 'categorie')
            ->leftJoin('status.type', 'type')
            ->andWhere('categorie.nom = :categoryName')
            ->andWhere('status.defaultForCategory = 1')
            ->setParameter("categoryName", $categoryName);

        $res = $queryBuilder
            ->getQuery()
            ->getResult();

        return array_reduce($res, function (array $carry, $status) {
            $typeId = $status['typeId'] ?: 0;
            $carry[$typeId] = $status[0]->getId();
            return $carry;
        }, []);
    }

    public function findByCategorieNames(?array $categorieNames, $ordered = false, ?array $states = []) {
        $queryBuilder = $this->createQueryBuilder('status')
            ->join('status.categorie', 'categorie')
            ->andWhere('categorie.nom IN (:categorieNames)')
            ->setParameter("categorieNames", $categorieNames, Connection::PARAM_STR_ARRAY);

        if ($ordered) {
            $queryBuilder->orderBy('status.displayOrder', 'ASC');
        }

        if (!empty($states)) {
            $queryBuilder
                ->andWhere('status.state IN (:states)')
                ->setParameter('states', $states);
        }

        return $queryBuilder->getQuery()->execute();
    }

    public function findByCategoryNameAndStatusCodes($categoryName, $statusCodes) {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
        /** @lang DQL */
            "SELECT s
            FROM App\Entity\Statut s
            JOIN s.categorie c
            WHERE c.nom = :categoryName
            AND s.nom IN (:statusCodes)"
        );
        $query->setParameter("categoryName", $categoryName);
        $query->setParameter("statusCodes", $statusCodes, Connection::PARAM_STR_ARRAY);

        return $query->execute();
    }

    public function findOneByCategorieNameAndStatutCode($categorieName, $statutCode) {
        $queryBuilder = $this->createQueryBuilder('s');
        $queryBuilder
            ->join('s.categorie', 'c')
            ->where('c.nom = :categorieName AND s.code = :statutCode')
            ->setParameters([
                'categorieName' => $categorieName,
                'statutCode' => $statutCode
            ]);

        return $queryBuilder
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findOneByCategorieNameAndStatutState($categorieName, $state) {
        $queryBuilder = $this->createQueryBuilder('s');
        $queryBuilder
            ->join('s.categorie', 'c')
            ->where('c.nom = :categorieName AND s.state = :state')
            ->setParameters([
                'categorieName' => $categorieName,
                'state' => $state
            ]);

        return $queryBuilder
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findByCategoryAndStates(string $categoryName, array $states): array {
        $queryBuilder = $this->createQueryBuilder('status');
        $queryBuilder
            ->join('status.categorie', 'category')
            ->where('category.nom = :categoryName AND status.state IN (:states)')
            ->setParameter('categoryName', $categoryName)
            ->setParameter('states', $states);

        return $queryBuilder
            ->getQuery()
            ->getResult();
    }

    public function countSimilarLabels($category, $label, $type, $current = null) {
        $qb = $this->createQueryBuilder("s")
            ->select("COUNT(s)")
            ->where("s.nom LIKE :label")
            ->andWhere("s.categorie = :category")
            ->andWhere("s.type = :type")
            ->setParameter("category", $category)
            ->setParameter("type", $type)
            ->setParameter("label", $label);

        if ($current) {
            $qb->andWhere("s.id != :current")
                ->setParameter("current", $current);
        }

        return $qb->getQuery()->getSingleScalarResult();
    }

    public function countUsedById($id) {
        $queryBuilder = $this->createQueryBuilder('s');
        $exprBuilder = $queryBuilder->expr();

        $queryBuilder
            ->select('COUNT(s)')
            ->leftJoin('s.articles', 'a')
            ->leftJoin('s.collectes', 'c')
            ->leftJoin('s.demandes', 'dl')
            ->leftJoin('s.livraisons', 'ol')
            ->leftJoin('s.preparations', 'p')
            ->leftJoin('s.litiges', 'l')
            ->leftJoin('s.receptions', 'r')
            ->leftJoin('s.referenceArticles', 'ra')
            ->leftJoin('s.handlings', 'handling')
            ->leftJoin('s.dispatches', 'dispatch')
            ->leftJoin('s.transferRequests', 'transferRequest')
            ->leftJoin('s.transferOrders', 'transferOrder')
            ->leftJoin('s.arrivages', 'arrivals')
            ->where('s.id = :statusId')
            ->andWhere($exprBuilder->orX(
                'a IS NOT NULL',
                'c IS NOT NULL',
                'dl IS NOT NULL',
                'ol IS NOT NULL',
                'l IS NOT NULL',
                'r IS NOT NULL',
                'ra IS NOT NULL',
                'handling IS NOT NULL',
                'dispatch IS NOT NULL',
                'transferRequest IS NOT NULL',
                'transferOrder IS NOT NULL',
                'arrivals IS NOT NULL'
            ))
            ->setParameter('statusId', $id);

        return (int)$queryBuilder
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findByParamsAndFilters($params, $filters) {
        $qb = $this->createQueryBuilder('status');
        $exprBuilder = $qb->expr();

        $qb
            ->join('status.categorie', 'category')
            ->where('(' . $exprBuilder->orX(
                    'category.nom = :categoryLabel_arrivalDispute',
                    'category.nom = :categoryLabel_receptionDispute',
                    'category.nom = :categoryLabel_dispatch',
                    'category.nom = :categoryLabel_handling',
                    'category.nom = :categoryLabel_arrival',
                    'category.nom = :categoryLabel_purchaseRequest'
                ) . ')')
            ->setParameters([
                'categoryLabel_arrivalDispute' => CategorieStatut::LITIGE_ARR,
                'categoryLabel_receptionDispute' => CategorieStatut::LITIGE_RECEPT,
                'categoryLabel_dispatch' => CategorieStatut::DISPATCH,
                'categoryLabel_handling' => CategorieStatut::HANDLING,
                'categoryLabel_arrival' => CategorieStatut::ARRIVAGE,
                'categoryLabel_purchaseRequest' => CategorieStatut::PURCHASE_REQUEST,
            ]);

        $qb
            ->select('count(status)');
        // compte le nombre total d'éléments
        $countTotal = $qb->getQuery()->getSingleScalarResult();

        foreach ($filters as $filter) {
            switch ($filter['field']) {
                case 'statusEntity':
                    $qb
                        ->andWhere('category.id in (:categoryFilter)')
                        ->setParameter('categoryFilter', $filter['value']);
                    break;
            }
        }

        if (!empty($params)) {
            if (!empty($params->get('search'))) {
                $search = $params->get('search')['value'];
                if (!empty($search)) {
                    $qb
                        ->andWhere('(' .
                            $exprBuilder->orX(
                                'status.nom LIKE :value',
                                'status.comment LIKE :value',
                                'status.code LIKE :value'
                            )
                            . ')')
                        ->setParameter('value', '%' . $search . '%');
                }
            }

            $orderArray = $params->get('order');
            if (!empty($orderArray)) {
                foreach ($orderArray as $order) {
                    $dir = $order['dir'];
                    $column = $order['column'];
                    if (!empty($dir)) {
                        $key = $params->get('columns')[$column]['data'] ?? '';
                        $column = (self::DtToDbLabels[$key] ?? $key);
                        $qb->addOrderBy('status.' . $column, $dir);
                    }
                }
            }
        }

        $qb
            ->select('count(status)');
        // compte éléments filtrés
        $countFiltered = $qb->getQuery()->getSingleScalarResult();

        $qb
            ->select('status');

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

    public function findStatusByType(string $categoryLabel,
                                     Type $type = null,
                                     array $stateFilters = []) {
        $qb = $this->createQueryBuilder('status')
            ->join('status.categorie', 'category')
            ->where('category.nom = :categoryLabel')
            ->addOrderBy('status.displayOrder', 'ASC')
            ->setParameter('categoryLabel', $categoryLabel);

        if (!empty($stateFilters)) {
            $qb
                ->andWhere('status.state IN (:stateIds)')
                ->setParameter(':stateIds', $stateFilters);
        }

        if ($type) {
            $qb
                ->andWhere("status.type = :type OR status.type IS NULL")
                ->setParameter("type", $type);
        }

        return $qb->getQuery()->getResult();
    }

    public function getMobileStatus(bool $dispatchStatus, bool $handlingStatus): array {
        if ($dispatchStatus || $handlingStatus) {
            $queryBuilder = $this->createQueryBuilder('status')
                ->select('status.id AS id')
                ->addSelect('status.nom AS label')
                ->addSelect('status_category.nom AS category')
                ->addSelect('status.commentNeeded AS commentNeeded')
                ->addSelect('type.id AS typeId')
                ->addSelect("(
                    CASE
                        WHEN status.state = :treatedState THEN 'treated'
                        WHEN status.state = :partialState THEN 'partial'
                        WHEN status.state = :notTreatedState THEN 'notTreated'
                        WHEN status.state = :inProgressState THEN 'inProgress'
                        ELSE ''
                    END
                ) AS state")
                ->addSelect('status.displayOrder AS displayOrder')
                ->join('status.categorie', 'status_category')
                ->leftJoin('status.type', 'type')
                ->orderBy('status.displayOrder', 'ASC')
                ->setParameter('treatedState', Statut::TREATED)
                ->setParameter('partialState', Statut::PARTIAL)
                ->setParameter('inProgressState', Statut::IN_PROGRESS)
                ->setParameter('notTreatedState', Statut::NOT_TREATED);

            if ($dispatchStatus) {
                $queryBuilder
                    ->where('status_category.nom = :dispatchCategoryLabel')
                    ->setParameter('dispatchCategoryLabel', CategorieStatut::DISPATCH);
            }

            if ($handlingStatus) {
                $queryBuilder
                    ->orWhere('status_category.nom = :handlingCategoryLabel')
                    ->setParameter('handlingCategoryLabel', CategorieStatut::HANDLING);
            }

            return $queryBuilder
                ->getQuery()
                ->getResult();
        } else {
            return [];
        }
    }
}
