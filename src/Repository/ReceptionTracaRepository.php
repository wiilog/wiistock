<?php

namespace App\Repository;

use App\Entity\ReceptionTraca;
use App\Helper\QueryCounter;
use DateTime;
use Doctrine\ORM\EntityRepository;

/**
 * @method ReceptionTraca|null find($id, $lockMode = null, $lockVersion = null)
 * @method ReceptionTraca|null findOneBy(array $criteria, array $orderBy = null)
 * @method ReceptionTraca[]    findAll()
 * @method ReceptionTraca[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ReceptionTracaRepository extends EntityRepository
{
    private const DtToDbLabels = [
        'date' => 'dateCreation',
        'Arrivage' => 'arrivage',
        'Réception' => 'number',
        'Utilisateur' => 'user',
    ];

    /**
     * @param $firstDay
     * @param $lastDay
     * @return mixed
     * @throws \Exception
     */
    public function countByDays($firstDay, $lastDay) {
        $from = new \DateTime(str_replace("/", "-", $firstDay) ." 00:00:00");
        $to   = new \DateTime(str_replace("/", "-", $lastDay) ." 23:59:59");
        $em = $this->getEntityManager();
        $query = $em->createQuery(
            "SELECT COUNT(r.id) as count, r.dateCreation as date
			FROM App\Entity\ReceptionTraca r
			WHERE r.dateCreation BETWEEN :firstDay AND :lastDay
			GROUP BY r.dateCreation"
        )->setParameters([
            'lastDay' => $to,
            'firstDay' => $from
        ]);
        return $query->execute();
    }

    /**
     * @param array|null $params
     * @param array|null $filters
     * @return array
     * @throws \Exception
     */
    public function findByParamsAndFilters($params, $filters)
    {
        $qb = $this->createQueryBuilder("r");

        $countTotal = QueryCounter::count($qb, 'r');

        // filtres sup
        foreach ($filters as $filter) {
            switch($filter['field']) {
                case 'utilisateurs':
                    $value = explode(',', $filter['value']);
                    $qb
                        ->join('r.user', 'u')
                        ->andWhere("u.id in (:userId)")
                        ->setParameter('userId', $value);
                    break;
                case 'arrivage_string':
                    $qb
                        ->andWhere('r.arrivage LIKE :arrivage_string')
                        ->setParameter('arrivage_string', '%' . $filter['value'] . '%');
                    break;
                case 'reception_string':
                    $qb
                        ->andWhere('r.number LIKE :reception_string')
                        ->setParameter('reception_string', '%' . $filter['value'] . '%');
                    break;
                case 'dateMin':
                    $qb
                        ->andWhere('r.dateCreation >= :dateMin')
                        ->setParameter('dateMin', $filter['value']. " 00:00:00");
                    break;
                case 'dateMax':
                    $qb
                        ->andWhere('r.dateCreation <= :dateMax')
                        ->setParameter('dateMax', $filter['value'] . " 23:59:59");
                    break;
            }
        }

        //Filter search
        if (!empty($params)) {
            if (!empty($params->get('search'))) {
                $search = $params->get('search')['value'];
                if (!empty($search)) {
                    $qb
                        ->leftJoin('r.user', 'u2')
                        ->andWhere("
						DATE_FORMAT(r.dateCreation, '%d/%m/%Y') LIKE :value OR
						DATE_FORMAT(r.dateCreation, '%H:%i:%S') LIKE :value OR
						u2.username LIKE :value OR
						r.arrivage LIKE :value OR
						r.number LIKE :value
						")
                        ->setParameter('value', '%' . $search . '%');
                }
            }

            if (!empty($params->get('order')))
            {
                $order = $params->get('order')[0]['dir'];
                if (!empty($order))
                {
                    $column = self::DtToDbLabels[$params->get('columns')[$params->get('order')[0]['column']]['data']];

                    if ($column === 'dateCreation') {
                        $qb
                            ->orderBy('r.dateCreation', $order);
                    } else if ($column === 'arrivage') {
                        $qb
                            ->orderBy('r.arrivage', $order);
                    } else if ($column === 'user') {
                        $qb
                            ->leftJoin('r.user', 'u3')
                            ->orderBy('u3.username', $order);
                    } else {
                        $qb
                            ->orderBy('r.' . $column, $order);
                    }
                }
            }
        }

        // compte éléments filtrés
        $countFiltered = QueryCounter::count($qb, 'r');

        if ($params) {
            if (!empty($params->get('start'))) $qb->setFirstResult($params->get('start'));
            if (!empty($params->get('length'))) $qb->setMaxResults($params->get('length'));
        }

        $query = $qb->getQuery();

        return [
            'data' => $query ? $query->getResult() : null ,
            'count' => $countFiltered,
            'total' => $countTotal
        ];
    }

    public function iterateBetween(DateTime $start,
                                   DateTime $end) {
        $queryBuilder = $this->createQueryBuilder('tracking_reception');
        $exprBuilder = $queryBuilder->expr();
        $iterator = $this->createQueryBuilder('tracking_reception')
            ->where($exprBuilder->between('tracking_reception.dateCreation', ':start', ':end'))
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->getQuery()
            ->iterate();

        foreach($iterator as $item) {
            // $item [index => article array]
            yield array_pop($item);
        }
    }
}
