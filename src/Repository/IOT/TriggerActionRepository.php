<?php

namespace App\Repository\IOT;

use App\Entity\FiltreSup;
use App\Entity\IOT\TriggerAction;
use App\Entity\PurchaseRequest;
use App\Helper\QueryCounter;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\EntityRepository;

/**
 * @method TriggerAction|null find($id, $lockMode = null, $lockVersion = null)
 * @method TriggerAction|null findOneBy(array $criteria, array $orderBy = null)
 * @method TriggerAction[]    findAll()
 * @method TriggerAction[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TriggerActionRepository extends EntityRepository
{

    public function findByParamsAndFilters($params) {

        $qb = $this->createQueryBuilder("trigger_action");
        $total = QueryCounter::count($qb, "trigger_action");

        //Filter search
        if (!empty($params)) {
            if (!empty($params->get('search'))) {
                $search = $params->get('search')['value'];
                if (!empty($search)) {
                    $exprBuilder = $qb->expr();
                    $qb
                        ->andWhere('(' .
                            $exprBuilder->orX(
                                'search_sensorWrapper.name LIKE :value',
                            )
                            . ')')
                        ->leftJoin('trigger_action.sensorWrapper', 'search_sensorWrapper')
                        ->setParameter('value', '%' . $search . '%');
                }
            }

            if (!empty($params->get('order')))
            {
                $order = $params->get('order')[0]['dir'];
                if (!empty($order))
                {
                    $column = $params->get('columns')[$params->get('order')[0]['column']]['data'];

                    if ($column === 'sensorWrapper') {
                        $qb
                            ->leftJoin('trigger_action.sensorWrapper', 'sensor_wrapper')
                            ->orderBy('sensor_wrapper.name', $order);
                    }
                    else if ($column === 'template') {
                        $qb
                            ->leftJoin('trigger_action.alertTemplate', 'alert_template')
                            ->leftJoin('trigger_action.requestTemplate', 'request_template')
                            ->orderBy('IFNULL(alert_template.name, request_template.name)', $order);
                    }
                }
            }
        }

        // compte éléments filtrés
        $countFiltered = QueryCounter::count($qb, 'trigger_action');

        if ($params) {
            if (!empty($params->get('start'))) $qb->setFirstResult($params->get('start'));
            if (!empty($params->get('length'))) $qb->setMaxResults($params->get('length'));
        }

        return [
            'data' => $qb->getQuery()->getResult(),
            'count' => $countFiltered,
            'total' => $total
        ];
    }

}
