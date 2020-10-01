<?php

namespace App\Helper;

use Doctrine\ORM\QueryBuilder;
use RuntimeException;

class QueryCounter {

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

        $count = $query->select("COUNT($alias)")
            ->getQuery()
            ->getSingleScalarResult();

        $query->resetDQLPart("select");
        foreach($original as $select) {
            $query->addSelect($select->getParts());
        }

        return $count;
    }

}
