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
    public static function count(QueryBuilder $query, ?string $alias = null): int {
        $original = $query->getDQLPart("select");

        if(!$alias) {
            $select = $original[0];
            $parts = $select->getParts();

            if(count($parts) == 1) {
                $alias = $parts[0];
            }
        }

        if(!$alias) {
            throw new RuntimeException("Unable to deduce select, provide the selected table");
        }

        $countQuery = clone $query;

        return $countQuery
            ->resetDQLPart('orderBy')
            ->select("COUNT($alias) AS __query_count")
            ->getQuery()
            ->getSingleResult()["__query_count"];
    }

}
