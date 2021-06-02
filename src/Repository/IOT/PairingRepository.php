<?php

namespace App\Repository\IOT;

use App\Entity\IOT\Pairing;
use App\Entity\IOT\Sensor;
use App\Helper\QueryCounter;
use Doctrine\ORM\EntityRepository;

/**
 * @method Pairing|null find($id, $lockMode = null, $lockVersion = null)
 * @method Pairing|null findOneBy(array $criteria, array $orderBy = null)
 * @method Pairing[]    findAll()
 * @method Pairing[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PairingRepository extends EntityRepository
{
    public function findByParams($params, Sensor $sensor)
    {

        $qb = $this->createQueryBuilder("pairing")
            ->leftJoin('pairing.sensorWrapper', 'sensor_wrapper')
            ->leftJoin('sensor_wrapper.sensor', 'sensor')
            ->where('sensor = :sensor')
            ->setParameter('sensor', $sensor);

        $total = QueryCounter::count($qb, "pairing");

        if (!empty($params)) {
            if (!empty($params->get('search'))) {
                $search = $params->get('search')['value'];
                if (!empty($search)) {
                    $exprBuilder = $qb->expr();
                    $qb
                        ->andWhere('(' .
                            $exprBuilder->orX(
                                "DATE_FORMAT(pairing.start, '%d/%m/%Y') LIKE :value",
                                "DATE_FORMAT(pairing.end, '%d/%m/%Y') LIKE :value",
                                'search_article.barCode LIKE :value',
                                'search_collectOrder.numero LIKE :value',
                                'search_location.label LIKE :value',
                                'search_pack.code LIKE :value',
                                'search_preparationOrder.numero LIKE :value',
                            )
                            . ')')
                        ->leftJoin('pairing.article', 'search_article')
                        ->leftJoin('pairing.collectOrder', 'search_collectOrder')
                        ->leftJoin('pairing.location', 'search_location')
                        ->leftJoin('pairing.pack', 'search_pack')
                        ->leftJoin('pairing.preparationOrder', 'search_preparationOrder')
                        ->setParameter('value', '%' . $search . '%');
                }
            }

            if (!empty($params->get('order'))) {
                $order = $params->get('order')[0]['dir'];
                if (!empty($order)) {
                    $column = $params->get('columns')[$params->get('order')[0]['column']]['data'];
                    switch ($column) {
                        case 'element':
                            $qb
                                ->orderBy('IFNULL(order_article.barCode, IFNULL(order_collectOrder.numero, IFNULL(order_location.label, IFNULL(order_pack.code, order_preparationOrder.numero))))', $order)
                                ->leftJoin('pairing.article', 'order_article')
                                ->leftJoin('pairing.collectOrder', 'order_collectOrder')
                                ->leftJoin('pairing.location', 'order_location')
                                ->leftJoin('pairing.pack', 'order_pack')
                                ->leftJoin('pairing.preparationOrder', 'order_preparationOrder');
                            break;
                        default:
                            if (property_exists(Pairing::class, $column)) {
                                $qb->orderBy('pairing.' . $column, $order);
                            }
                            break;
                    }
                }
            }
        }

        $countFiltered = QueryCounter::count($qb, 'pairing');

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
