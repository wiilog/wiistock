<?php

namespace App\Helper;

use App\Entity\TransferOrder;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\QueryBuilder;

class QueryCounter
{

    /**
     * @param QueryBuilder $query
     * @param string|null $alias
     * @return int
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    public static function count(QueryBuilder $query, string $alias): int
    {
        $countQuery = clone $query;

        return $countQuery
            ->resetDQLPart('orderBy')
            ->resetDQLPart('groupBy')
            ->select("COUNT(DISTINCT $alias) AS __query_count")
            ->getQuery()
            ->getSingleResult()["__query_count"];
    }

    /**
     * @param EntityManagerInterface $entityManager
     * @param string $entity
     * @param array $statuses
     * @param array $types
     * @return int|mixed|string
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    public static function countByStatusesAndTypes(EntityManagerInterface $entityManager,
                                                   string $entity,
                                                   array $statuses = [],
                                                   array $types = [])
    {
        $qb = $entityManager->createQueryBuilder();

        $qb
            ->select("COUNT(${entity})")
            ->from("'App\Entity\'" . "'${entity}'", 'entity');

        if (!empty($statuses)) {
            $statusProperty = property_exists($entity, 'status') ? 'status' : 'statut';
            $qb
                ->leftJoin("entity.${statusProperty}", 'status')
                ->andWhere('status IN (:statuses)')
                ->setParameter('statuses', $statuses);
        }

        if (!empty($statuses)) {
            $qb
                ->leftJoin("entity.type", 'type')
                ->andWhere('type IN (:types)')
                ->setParameter('types', $types);
        }

        return $qb
            ->getQuery()
            ->getSingleScalarResult();

    }
}
