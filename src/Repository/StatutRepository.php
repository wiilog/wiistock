<?php

namespace App\Repository;

use App\Entity\CategorieStatut;
use App\Entity\Statut;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;

/**
 * @method Statut|null find($id, $lockMode = null, $lockVersion = null)
 * @method Statut|null findOneBy(array $criteria, array $orderBy = null)
 * @method Statut[]    findAll()
 * @method Statut[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class StatutRepository extends EntityRepository
{

    private const DtToDbLabels = [
        'category' => 'categorie',
        'label' => 'nom',
        'comment' => 'comment',
        'defaultStatus' => 'defaultForCategory',
        'treatedStatus' => 'treated',
        'notifToDeclarant' => 'sendNotifToDeclarant',
        'order' => 'displayOrder'
    ];

    /**
     * @param string $categorieName
     * @param bool $ordered
     * @param bool $onlyNotTreated
     * @param null $type
     * @return Statut[]
     */
    public function findByCategorieName($categorieName,
                                        $ordered = false,
                                        $onlyNotTreated = false,
                                        $type = null)
    {
        $queryBuilder = $this->createQueryBuilder('status')
            ->join('status.categorie', 'categorie')
            ->andWhere('categorie.nom = :categorieName');

        if ($onlyNotTreated) {
            $queryBuilder
                ->andWhere('status.treated = false');
        }

        if (!empty($type)) {
            $queryBuilder
                ->andWhere('status.type = :type')
                ->setParameter('type', $type);
        }

        if ($ordered) {
            $queryBuilder->orderBy('status.displayOrder', 'ASC');
        }

        $queryBuilder
            ->setParameter("categorieName", $categorieName);

        return $queryBuilder
            ->getQuery()
            ->execute();
    }

    /**
     * Status for given category grouped by
     * @param string $categoryName
     * @return Statut[]
     */
    public function getIdDefaultsByCategoryName(string $categoryName): array
    {
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

    /**
     * @param array $categorieNames
     * @param bool $ordered
     * @return Statut[]
     */
    public function findByCategorieNames($categorieNames, $ordered = false)
    {
        $queryBuilder = $this->createQueryBuilder('status')
            ->join('status.categorie', 'categorie')
            ->andWhere('categorie.nom IN (:categorieNames)')
            ->setParameter("categorieNames", $categorieNames, Connection::PARAM_STR_ARRAY);

        if ($ordered) {
            $queryBuilder->orderBy('status.displayOrder', 'ASC');
        }

        return $queryBuilder->getQuery()->execute();
    }

    /**
     * @param string $categoryName
     * @param string[] $statusCodes
     * @return mixed
     */
    public function findByCategoryNameAndStatusCodes($categoryName, $statusCodes)
    {
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


    /**
     * @param string $categorieName
     * @param string $statutCode
     * @return Statut | null
     * @throws NonUniqueResultException
     */
    public function findOneByCategorieNameAndStatutCode($categorieName, $statutCode)
    {
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

    /**
     * @param string $categorieName
     * @param string[] $listStatusName
     * @return Statut[]
     */
    public function getIdByCategorieNameAndStatusesNames($categorieName, $listStatusName)
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
            "SELECT s.id
			  FROM App\Entity\Statut s
			  JOIN s.categorie c
			  WHERE c.nom = :categorieName AND s.nom IN (:listStatusName)
          "
        );

        $query
            ->setParameter('categorieName', $categorieName)
            ->setParameter('listStatusName', $listStatusName, Connection::PARAM_STR_ARRAY);

        return array_column($query->execute(), 'id');
    }

    /**
     * @param string $categorieName
     * @param string $statusName
     * @return int
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    public function getOneIdByCategorieNameAndStatusName($categorieName, $statusName)
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
            "SELECT s.id
			  FROM App\Entity\Statut s
			  JOIN s.categorie c
			  WHERE c.nom = :categorieName AND s.nom = :statusName
          "
        );

        $query
            ->setParameters([
                'categorieName' => $categorieName,
                'statusName' => $statusName
            ]);

        return $query->getSingleScalarResult();
    }

    /**
     * @param string $label
     * @param string $category
     * @return int
     * @throws NonUniqueResultException
     * @throws NoResultException
     */
    public function countByLabelAndCategory($label, $category)
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
        /** @lang DQL */
            "SELECT COUNT(s)
            FROM App\Entity\Statut s
            WHERE LOWER(s.nom) = :label AND s.categorie = :category
           "
        )->setParameters([
            'label' => $label,
            'category' => $category
        ]);

        return $query->getSingleScalarResult();
    }

    public function countByLabelDiff($label, $statusLabel, $category)
    {
        $em = $this->getEntityManager();

        $query = $em->createQuery(
        /** @lang DQL */
            "SELECT count(s)
            FROM App\Entity\Statut s
            WHERE s.nom = :label AND s.nom != :statusLabel AND s.categorie = :category"
        )->setParameters([
            'label' => $label,
            'statusLabel' => $statusLabel,
            'category' => $category
        ]);

        return $query->getSingleScalarResult();
    }

    /**
     * @param int $id
     * @return int
     * @throws NonUniqueResultException
     * @throws NoResultException
     */
    public function countUsedById($id)
    {
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
                'dispatch IS NOT NULL'
            ))
            ->setParameter('statusId', $id);

        return (int) $queryBuilder
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @param $params
     * @param array|null $filters
     * @return array
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    public function findByParamsAndFilters($params, $filters)
    {
        $qb = $this->createQueryBuilder('status');
        $exprBuilder = $qb->expr();

        $qb
            ->join('status.categorie', 'category')
            ->where('(' . $exprBuilder->orX(
                    'category.nom = :categoryLabel_arrivalDispute',
                    'category.nom = :categoryLabel_receptionDispute',
                    'category.nom = :categoryLabel_dispatch',
                    'category.nom = :categoryLabel_handling'
                ) . ')')
            ->setParameters([
                'categoryLabel_arrivalDispute' => CategorieStatut::LITIGE_ARR,
                'categoryLabel_receptionDispute' => CategorieStatut::LITIGE_RECEPT,
                'categoryLabel_dispatch' => CategorieStatut::DISPATCH,
                'categoryLabel_handling' => CategorieStatut::HANDLING
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

    public function findTreatedStatusByType($categoryLabel, $type, $orderedBy = false)
    {
        $qb = $this->createQueryBuilder('status');

        $qb
            ->select('status')
            ->join('status.categorie', 'category')
            ->where('category.nom = :categoryLabel')
            ->andWhere('status.treated = true')
            ->andWhere('status.type = :type')
            ->setParameters([
                'categoryLabel' => $categoryLabel,
                'type' => $type
            ]);

        if ($orderedBy) {
            $qb->orderBy('status.displayOrder', 'ASC');
        }

        return $qb
            ->getQuery()
            ->getResult();
    }

    public function getMobileStatus(): array {
        $queryBuilder = $this->createQueryBuilder('status')
            ->select('status.id AS id')
            ->addSelect('status.nom AS label')
            ->addSelect('status_category.nom AS category')
            ->addSelect('type.id AS typeId')
            ->addSelect('status.treated AS treated')
            ->addSelect('status.displayOrder AS displayOrder')
            ->join('status.categorie', 'status_category')
            ->leftJoin('status.type', 'type')
            ->orderBy('status.displayOrder', 'ASC')
            ->where('status_category.nom = :dispatchCategory')
            ->setParameter('dispatchCategory', CategorieStatut::DISPATCH);
        return $queryBuilder
            ->getQuery()
            ->getResult();
    }

    public function getIdNotTreatedByCategory(string $categoryLabel) {
        return array_map(
            function($handling) {
                return $handling['id'];
            },
            $this->createQueryBuilder('status')
                ->select('status.id')
                ->leftJoin('status.categorie', 'category')
                ->where('status.treated = false')
                ->andWhere('category.nom LIKE :categoryLabel')
                ->setParameter('categoryLabel', $categoryLabel)
                ->getQuery()
                ->getResult()
        );
    }
}
