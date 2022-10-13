<?php

namespace App\Repository\IOT;

use App\Entity\IOT\RequestTemplate;
use App\Helper\QueryBuilderHelper;
use Doctrine\ORM\EntityRepository;
use Symfony\Component\HttpFoundation\InputBag;

/**
 * @method RequestTemplate|null find($id, $lockMode = null, $lockVersion = null)
 * @method RequestTemplate|null findOneBy(array $criteria, array $orderBy = null)
 * @method RequestTemplate[]    findAll()
 * @method RequestTemplate[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class RequestTemplateRepository extends EntityRepository {

    public function findByParamsAndFilters(InputBag $params) {
        $queryBuilder = $this->createQueryBuilder("request_template");

        $countTotal = QueryBuilderHelper::count($queryBuilder, "request_template");

        //Filter search
        if (!empty($params->all('search'))) {
            $search = $params->all('search')['value'];
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

        if (!empty($params->all('order'))) {
            $order = $params->all('order')[0]['dir'];
            if (!empty($order)) {
                $column = $params->all('columns')[$params->all('order')[0]['column']]['data'];
                $queryBuilder->orderBy("request_template.$column", $order);
            }
        }

        $countFiltered = QueryBuilderHelper::count($queryBuilder, "request_template");

        if ($params->getInt('start')) $queryBuilder->setFirstResult($params->getInt('start'));
        if ($params->getInt('length')) $queryBuilder->setMaxResults($params->getInt('length'));

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
