<?php

namespace App\Repository\IOT;

use App\Entity\IOT\RequestTemplate;
use App\Helper\QueryCounter;
use Doctrine\ORM\EntityRepository;

/**
 * @method RequestTemplate|null find($id, $lockMode = null, $lockVersion = null)
 * @method RequestTemplate|null findOneBy(array $criteria, array $orderBy = null)
 * @method RequestTemplate[]    findAll()
 * @method RequestTemplate[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class RequestTemplateRepository extends EntityRepository {

    public function findByParamsAndFilters($params) {
        $queryBuilder = $this->createQueryBuilder("request_template");

        $countTotal = QueryCounter::count($queryBuilder, "request_template");

        //Filter search
        if (!empty($params)) {
            if (!empty($params->get('search'))) {
                $search = $params->get('search')['value'];
                if (!empty($search)) {
                    $queryBuilder
                        ->join("request_template.type", "search_type")
                        ->andWhere($queryBuilder->expr()->orX(
                            "request_template.name LIKE :value",
                            "search_type.label LIKE :value",
                        ))
                        ->setParameter('value', '%' . $search . '%');
                }
            }

            if (!empty($params->get('order'))) {
                $order = $params->get('order')[0]['dir'];
                if (!empty($order)) {
                    $column = $params->get('columns')[$params->get('order')[0]['column']]['data'];
                    $queryBuilder->orderBy("request_template.$column", $order);
                }
            }
        }

        $countFiltered = QueryCounter::count($queryBuilder, "request_template");

        if ($params) {
            if (!empty($params->get('start'))) $queryBuilder->setFirstResult($params->get('start'));
            if (!empty($params->get('length'))) $queryBuilder->setMaxResults($params->get('length'));
        }

        $query = $queryBuilder->getQuery();
        return [
            'data' => $query ? $query->getResult() : null,
            'count' => $countFiltered,
            'total' => $countTotal
        ];
    }

    public function getTemplateForSelect(){
        $qb = $this->createQueryBuilder("request_template");

        $qb->select("request_template.id AS id")
            ->addSelect("request_template.name AS text");

        return $qb->getQuery()->getResult();
    }

}
