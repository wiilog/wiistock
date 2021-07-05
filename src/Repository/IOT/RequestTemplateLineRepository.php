<?php

namespace App\Repository\IOT;

use App\Entity\IOT\CollectRequestTemplate;
use App\Entity\IOT\DeliveryRequestTemplate;
use App\Entity\IOT\RequestTemplate;
use App\Entity\IOT\RequestTemplateLine;
use Doctrine\ORM\EntityRepository;
use Symfony\Component\HttpFoundation\ParameterBag;

/**
 * @method RequestTemplateLine|null find($id, $lockMode = null, $lockVersion = null)
 * @method RequestTemplateLine|null findOneBy(array $criteria, array $orderBy = null)
 * @method RequestTemplateLine[]    findAll()
 * @method RequestTemplateLine[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class RequestTemplateLineRepository extends EntityRepository {

    public function findByParams(RequestTemplate $requestTemplate, ParameterBag $params) {
        $qb = $this->createQueryBuilder("line");

        if ($requestTemplate instanceof DeliveryRequestTemplate) {
            $qb->andWhere("line.deliveryRequestTemplate = :requestTemplate")
                ->setParameter("requestTemplate", $requestTemplate);
        } else if ($requestTemplate instanceof CollectRequestTemplate) {
            $qb->andWhere("line.collectRequestTemplate = :requestTemplate")
                ->setParameter("requestTemplate", $requestTemplate);
        }


        if (!empty($params)) {
            if(!empty($params->get('search'))) {
                $search = $params->get('search')['value'];
                if(!empty($search)) {
                    $qb->join("line.reference", "search_reference")
                        ->join("line.location", "search_location")
                        ->andWhere($qb->expr()->orX(
                            "search_reference.reference LIKE :value",
                            "search_reference.libelle LIKE :value",
                            "search_location.label LIKE :value",
                            "line.quantityToTake LIKE :value",
                        ))
                        ->setParameter("value", '%' . str_replace('_', '\_', $search) . '%');
                }
            }

            if (!empty($params->get('order'))) {
                $order = $params->get('order')[0]['dir'];
                if (!empty($order)) {
                    $column = $params->get('columns')[$params->get('order')[0]['column']]['data'];

                    if ($column === 'reference') {
                        $qb->join("line.reference", "order_reference_reference")
                            ->addOrderBy("order_reference_reference.reference", $order);
                    } else if ($column === 'label') {
                        $qb->join("line.reference", "order_reference_label")
                            ->addOrderBy("order_reference_label.libelle", $order);
                    } else if ($column === 'location') {
                        $qb->join("line.reference", "order_location")
                            ->addOrderBy("order_location.label", $order);
                    } else if ($column === 'quantity') {
                        $qb->addOrderBy("line.quantityToTake", $order);
                    }
                }
            }
        }

        if (!empty($params)) {
            if (!empty($params->get('start'))) $qb->setFirstResult($params->get('start'));
            if (!empty($params->get('length'))) $qb->setMaxResults($params->get('length'));
        }

        return $qb->getQuery()->getResult();
    }

}
