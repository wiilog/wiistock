<?php

namespace App\Helper;

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
                                                   array $types = [],
                                                   array $statuses = [])
    {
        if (!empty($statuses) && !empty($types)) {
            $qb = $entityManager->createQueryBuilder();
            $statusProperty = property_exists($entity, 'status') ? 'status' : 'statut';

            $qb
                ->select("COUNT(entity)")
                ->from($entity, 'entity')
                ->innerJoin("entity.${statusProperty}", 'status')
                ->innerJoin("entity.type", 'type')
                ->andWhere('status IN (:statuses)')
                ->andWhere('type IN (:types)')
                ->setParameter('types', $types)
                ->setParameter('statuses', $statuses);

            return $qb
                ->getQuery()
                ->getSingleScalarResult();
        }
        else {
            return [];
        }
    }
}
