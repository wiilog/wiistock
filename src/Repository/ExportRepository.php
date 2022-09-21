<?php

namespace App\Repository;

use App\Helper\QueryCounter;
use Doctrine\ORM\EntityRepository;
use Symfony\Component\HttpFoundation\InputBag;

class ExportRepository extends EntityRepository
{

    public function findByParamsAndFilters(InputBag $params, $filters)
    {
        $qb = $this->createQueryBuilder("export")
            ->orderBy("export.createdAt", "DESC");

        $countTotal = QueryCounter::count($qb, "export");

        // filtres sup
        foreach ($filters as $filter) {
            switch ($filter["field"]) {
                case "statut":
                    $value = explode(",", $filter["value"]);

                    $qb->join("export.status", "status_filter")
                        ->andWhere("status_filter.id in (:status)")
                        ->setParameter("status", $value);
                    break;
                case "utilisateurs":
                    $value = explode(",", $filter["value"]);

                    $qb->join("export.creator", "creator_filter")
                        ->andWhere("creator_filter.id in (:creator)")
                        ->setParameter("creator", $value);
                    break;
                case "dateMin":
                    $qb->andWhere("export.beganAt >= :dateMin OR export.endedAt >= :dateMin")
                        ->setParameter("dateMin", "${$filter['value']} 00:00:00");
                    break;
                case "dateMax":
                    $qb->andWhere("export.beganAt <= :dateMax OR export.endedAt <= :dateMax")
                        ->setParameter("dateMax", "${$filter['value']} 23:59:59");
                    break;
            }
        }

        //Filter search
        if (!empty($params)) {
            if (!empty($params->all("search"))) {
                $search = $params->all("search")["value"];
                if (!empty($search)) {
                    $exprBuilder = $qb->expr();
                    $qb
                        ->leftJoin("export.status", "status_search")
                        ->leftJoin("export.user", "user_search")
                        ->andWhere($exprBuilder->orX(
                            "status_search.nom LIKE :value",
                            "user_search.username LIKE :value",
                            "export.entity LIKE :value"
                        ))
                        ->setParameter("value", "%$search%");
                }
            }

            if (!empty($params->all("order"))) {
                $order = $params->all("order")[0]["dir"];
                if (!empty($order)) {
                    $column = $params->all('columns')[$params->all('order')[0]['column']]['data'];
                    switch ($column) {
                        case "actions":
                            break;
                        case "status":
                            $qb
                                ->leftJoin("i.status", "status_order")
                                ->orderBy("status_order.nom", $order);
                            break;
                        case "user":
                            $qb
                                ->leftJoin("i.user", "user_order")
                                ->orderBy("user_order.username", $order);
                            break;
                        default:
                            $qb->orderBy('i.' . $column, $order);
                    }
                }
            }
        }

        // compte éléments filtrés
        $countFiltered = QueryCounter::count($qb, "export");

        if ($params->getInt("start")) $qb->setFirstResult($params->getInt("start"));
        if ($params->getInt("length")) $qb->setMaxResults($params->getInt("length"));

        $query = $qb->getQuery();

        return [
            "data" => $query ? $query->getResult() : null,
            "count" => $countFiltered,
            "total" => $countTotal
        ];
    }


}
