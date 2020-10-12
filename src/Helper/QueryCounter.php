<?php

namespace App\Helper;

use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\QueryBuilder;
use RuntimeException;

class QueryCounter {

    /**
     * @param QueryBuilder $query
     * @param string|null $alias
     * @return int
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    public static function count(QueryBuilder $query, string $alias): int {
        $countQuery = clone $query;

        return $countQuery
            ->resetDQLPart('orderBy')
            ->select("COUNT(DISTINCT $alias) AS __query_count")
            ->getQuery()
            ->getSingleResult()["__query_count"];
    }

}
