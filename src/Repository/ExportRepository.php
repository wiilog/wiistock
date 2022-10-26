<?php

namespace App\Repository;

use App\Helper\QueryBuilderHelper;
use App\Entity\Export;
use App\Entity\Type;
use Doctrine\ORM\EntityRepository;
use Symfony\Component\HttpFoundation\InputBag;

class ExportRepository extends EntityRepository
{

    public function findScheduledExports() {
        return $this->createQueryBuilder("export")
            ->join("export.type", "type")
            ->join("export.status", "status")
            ->where("type.label = :type")
            ->andWhere("status.code = :status")
            ->setParameter("type", Type::LABEL_SCHEDULED_EXPORT)
            ->setParameter("status", Export::STATUS_SCHEDULED)
            ->getQuery()
            ->getResult();
    }

    public function findByParamsAndFilters(InputBag $params, $filters)
    {
        $qb = $this->createQueryBuilder("export")
            ->orderBy("export.createdAt", "DESC");

        $countTotal = QueryBuilderHelper::count($qb, "export");

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
                        ->setParameter("dateMin", "{$filter['value']} 00:00:00");
                    break;
                case "dateMax":
                    $qb->andWhere("export.beganAt <= :dateMax OR export.endedAt <= :dateMax")
                        ->setParameter("dateMax", "{$filter['value']} 23:59:59");
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
                        ->leftJoin("export.type", "type_search")
                        ->leftJoin("export.status", "status_search")
                        ->leftJoin("export.creator", "creator_search")
                        ->andWhere($exprBuilder->orX(
                            "type_search.label LIKE :value",
                            "status_search.nom LIKE :value",
                            "creator_search.username LIKE :value",
                            "CONCAT(export.entity, 's') LIKE :value",
                            "DATE_FORMAT(export.createdAt, '%d/%m/%Y') LIKE :value",
                            "DATE_FORMAT(export.beganAt, '%d/%m/%Y') LIKE :value",
                            "DATE_FORMAT(export.endedAt, '%d/%m/%Y') LIKE :value",
                            "DATE_FORMAT(export.nextExecution, '%d/%m/%Y') LIKE :value",
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
                                ->leftJoin("export.status", "status_order")
                                ->orderBy("status_order.nom", $order);
                            break;
                        case "user":
                            $qb
                                ->leftJoin("export.creator", "creator_order")
                                ->orderBy("creator_order.username", $order);
                            break;
                        case "type":
                            $qb
                                ->leftJoin("export.type", "type_order")
                                ->orderBy("type_order.label", $order);
                            break;
                        default:
                            if (property_exists(Export::class, $column)) {
                                $qb->orderBy('export.' . $column, $order);
                            }
                            break;
                    }
                }
            }
        }

        // compte éléments filtrés
        $countFiltered = QueryBuilderHelper::count($qb, "export");

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
