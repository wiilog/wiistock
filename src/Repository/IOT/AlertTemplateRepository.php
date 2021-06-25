<?php

namespace App\Repository\IOT;

use App\Entity\IOT\AlertTemplate;
use App\Helper\QueryCounter;
use Doctrine\ORM\EntityRepository;

/**
 * @method AlertTemplate|null find($id, $lockMode = null, $lockVersion = null)
 * @method AlertTemplate|null findOneBy(array $criteria, array $orderBy = null)
 * @method AlertTemplate[]    findAll()
 * @method AlertTemplate[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class AlertTemplateRepository extends EntityRepository
{

    public function getTemplateForSelect(){
        $qb = $this->createQueryBuilder("alert_template");

        $qb->select("alert_template.id AS id")
            ->addSelect("alert_template.name AS text");

        return $qb->getQuery()->getResult();
    }

    public function findByParams($params) {

        $qb = $this->createQueryBuilder("alert_template");
        $total = QueryCounter::count($qb, "alert_template");

        if (!empty($params)) {
            if (!empty($params->get('search'))) {
                $search = $params->get('search')['value'];
                if (!empty($search)) {
                    $exprBuilder = $qb->expr();
                    $qb
                        ->andWhere('(' .
                            $exprBuilder->orX(
                                'alert_template.name LIKE :value',
                                'alert_template.type LIKE :value',
                            )
                            . ')')
                        ->setParameter('value', '%' . $search . '%');
                }
            }

            if (!empty($params->get('order'))) {
                $order = $params->get('order')[0]['dir'];
                if (!empty($order)) {
                    $column = $params->get('columns')[$params->get('order')[0]['column']]['data'];
                    if (property_exists(AlertTemplate::class, $column)) {
                        $qb
                            ->orderBy('alert_template.' . $column, $order);
                    }
                }
            }
        }

        $countFiltered = QueryCounter::count($qb, 'alert_template');

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
