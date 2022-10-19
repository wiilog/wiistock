<?php

namespace App\Repository\IOT;

use App\Entity\IOT\AlertTemplate;
use App\Helper\QueryBuilderHelper;
use Doctrine\ORM\EntityRepository;
use Symfony\Component\HttpFoundation\InputBag;

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

    public function findByParams(InputBag $params) {

        $qb = $this->createQueryBuilder("alert_template");
        $total = QueryBuilderHelper::count($qb, "alert_template");

        if (!empty($params->all('search'))) {
            $search = $params->all('search')['value'];
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

        if (!empty($params->all('order'))) {
            $order = $params->all('order')[0]['dir'];
            if (!empty($order)) {
                $column = $params->all('columns')[$params->all('order')[0]['column']]['data'];
                if (property_exists(AlertTemplate::class, $column)) {
                    $qb
                        ->orderBy('alert_template.' . $column, $order);
                }
            }
        }

        $countFiltered = QueryBuilderHelper::count($qb, 'alert_template');

        if ($params->getInt('start')) $qb->setFirstResult($params->getInt('start'));
        if ($params->getInt('length')) $qb->setMaxResults($params->getInt('length'));

        return [
            'data' => $qb->getQuery()->getResult(),
            'count' => $countFiltered,
            'total' => $total
        ];
    }

}
