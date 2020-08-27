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
    /**
     * @param string $categorieName
     * @param bool $ordered
     * @param bool $onlyNotTreated
     * @return Statut[]
     */
    public function findByCategorieName($categorieName, $ordered = false, $onlyNotTreated = false)
    {
        $queryBuilder = $this->createQueryBuilder('status')
            ->join('status.categorie', 'categorie')
            ->andWhere('categorie.nom = :categorieName');

        if ($ordered) {
            $queryBuilder->orderBy('status.displayOrder', 'ASC');
        }

        if ($onlyNotTreated) {
            $queryBuilder
                ->andWhere('status.treated = 0');
        }

        $queryBuilder
            ->setParameter("categorieName", $categorieName);

        return $queryBuilder
            ->getQuery()
            ->execute();
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
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
        /** @lang DQL */
            "SELECT COUNT(s)
            FROM App\Entity\Statut s
            LEFT JOIN s.articles a
            LEFT JOIN s.collectes c
            LEFT JOIN s.demandes dl
            LEFT JOIN s.livraisons ol
            LEFT JOIN s.preparations p
            LEFT JOIN s.litiges l
            LEFT JOIN s.receptions r
            LEFT JOIN s.referenceArticles ra
            LEFT JOIN s.manutentions m
            WHERE a.statut = :id OR c.statut = :id OR dl.statut = :id OR ol.statut = :id OR p.statut = :id
            OR l.status = :id OR r.statut = :id OR ra.statut = :id OR m.statut = :id
           "
        )->setParameter('id', $id);

        return $query->getSingleScalarResult();
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
                    'category.nom = :litigeAr',
                    'category.nom = :litigeRe',
                    'category.nom = :ach'
                ) . ')')
            ->setParameters([
                'litigeAr' => CategorieStatut::LITIGE_ARR,
                'litigeRe' => CategorieStatut::LITIGE_RECEPT,
                'ach' => CategorieStatut::ACHEMINEMENT
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
                        ->andWhere('
                        status.nom LIKE :value
                        OR status.comment LIKE :value
                        OR status.code LIKE :value
                        ')
                        ->setParameter('value', '%' . $search . '%');
                }
            }
        }

        if ($params) {
            if (!empty($params->get('start'))) $qb->setFirstResult($params->get('start'));
            if (!empty($params->get('length'))) $qb->setMaxResults($params->get('length'));
        }

        $qb
            ->select('count(status)');
        // compte éléments filtrés
        $countFiltered = $qb->getQuery()->getSingleScalarResult();

        $qb
            ->select('status');

        $query = $qb->getQuery();

        return [
            'data' => $query ? $query->getResult() : null,
            'count' => $countFiltered,
            'total' => $countTotal
        ];
    }

    public function findDispatchStatusTreatedByType($type)
    {
        $qb = $this->createQueryBuilder('status');

        $qb
            ->select('status')
            ->join('status.categorie', 'category')
            ->join('status.type', 'type')
            ->where('category.nom = :ach')
            ->andWhere('status.treated = true')
            ->andWhere('type = :type')
            ->setParameters([
                'ach' => CategorieStatut::ACHEMINEMENT,
                'type' => $type
            ]);

        return $qb
            ->getQuery()
            ->getResult();
    }
}
