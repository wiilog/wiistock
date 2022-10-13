<?php

namespace App\Repository\IOT;

use App\Entity\IOT\TriggerAction;
use App\Helper\QueryBuilderHelper;
use Doctrine\ORM\EntityRepository;
use Symfony\Component\HttpFoundation\InputBag;

/**
 * @method TriggerAction|null find($id, $lockMode = null, $lockVersion = null)
 * @method TriggerAction|null findOneBy(array $criteria, array $orderBy = null)
 * @method TriggerAction[]    findAll()
 * @method TriggerAction[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TriggerActionRepository extends EntityRepository
{

    public function findByParamsAndFilters(InputBag $params)
    {
        $qb = $this->createQueryBuilder("trigger_action");
        $total = QueryBuilderHelper::count($qb, "trigger_action");

        //Filter search
        if (!empty($params)) {
            if (!empty($params->all('search'))) {
                $search = $params->all('search')['value'];
                if (!empty($search)) {
                    $exprBuilder = $qb->expr();
                    $qb
                        ->leftJoin('trigger_action.alertTemplate', 'search_alert_template')
                        ->leftJoin('trigger_action.requestTemplate', 'search_request_template')
                        ->andWhere($exprBuilder->orX(
                            'search_sensorWrapper.name LIKE :value',
                            "IFNULL(search_alert_template.name, search_request_template.name) LIKE :value"
                        ))
                        ->leftJoin('trigger_action.sensorWrapper', 'search_sensorWrapper')
                        ->setParameter('value', "%$search%");
                }
            }

            if (!empty($params->all('order'))) {
                $order = $params->all('order')[0]['dir'];
                if (!empty($order)) {
                    $column = $params->all('columns')[$params->all('order')[0]['column']]['data'];

                    if ($column === 'sensorWrapper') {
                        $qb
                            ->leftJoin('trigger_action.sensorWrapper', 'sensor_wrapper')
                            ->orderBy('sensor_wrapper.name', $order);
                    } else if ($column === 'template') {
                        $qb
                            ->leftJoin('trigger_action.alertTemplate', 'alert_template')
                            ->leftJoin('trigger_action.requestTemplate', 'request_template')
                            ->orderBy('IFNULL(alert_template.name, request_template.name)', $order);
                    }
                }
            }
        }

        // compte éléments filtrés
        $countFiltered = QueryBuilderHelper::count($qb, 'trigger_action');

        if ($params->getInt('start')) $qb->setFirstResult($params->getInt('start'));
        if ($params->getInt('length')) $qb->setMaxResults($params->getInt('length'));

        return [
            'data' => $qb->getQuery()->getResult(),
            'count' => $countFiltered,
            'total' => $total
        ];
    }

}
