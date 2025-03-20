<?php

namespace App\Repository\RequestTemplate;

use App\Entity\RequestTemplate\CollectRequestTemplate;
use App\Entity\RequestTemplate\DeliveryRequestTemplateTriggerAction;
use App\Entity\RequestTemplate\RequestTemplate;
use App\Entity\RequestTemplate\RequestTemplateLineReference;
use Doctrine\ORM\EntityRepository;
use Symfony\Component\HttpFoundation\InputBag;

/**
 * @method RequestTemplateLineReference|null find($id, $lockMode = null, $lockVersion = null)
 * @method RequestTemplateLineReference|null findOneBy(array $criteria, array $orderBy = null)
 * @method RequestTemplateLineReference[]    findAll()
 * @method RequestTemplateLineReference[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class RequestTemplateLineRepository extends EntityRepository {

    public function findByParams(RequestTemplate $requestTemplate,
                                 InputBag $params) {
        $qb = $this->createQueryBuilder("line");

        if ($requestTemplate instanceof DeliveryRequestTemplateTriggerAction) {
            $qb->andWhere("line.deliveryRequestTemplateTriggerAction = :requestTemplate")
                ->setParameter("requestTemplate", $requestTemplate);
        } else if ($requestTemplate instanceof CollectRequestTemplate) {
            $qb->andWhere("line.collectRequestTemplate = :requestTemplate")
                ->setParameter("requestTemplate", $requestTemplate);
        }


        if (!empty($params)) {
            if(!empty($params->all('search'))) {
                $search = $params->all('search')['value'];
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

            if (!empty($params->all('order'))) {
                $order = $params->all('order')[0]['dir'];
                if (!empty($order)) {
                    $column = $params->all('columns')[$params->all('order')[0]['column']]['data'];

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

        if ($params->getInt('start')) $qb->setFirstResult($params->getInt('start'));
        if ($params->getInt('length')) $qb->setMaxResults($params->getInt('length'));

        return $qb->getQuery()->getResult();
    }

}
