<?php

namespace App\Repository\Transport;

use App\Entity\FiltreSup;
use App\Entity\Transport\TransportCollectRequest;
use App\Entity\Transport\TransportDeliveryRequest;
use App\Entity\Transport\TransportRound;
use App\Helper\QueryCounter;
use DateTime;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query\Expr\Join;
use JetBrains\PhpStorm\ArrayShape;
use Symfony\Component\HttpFoundation\InputBag;
use WiiCommon\Helper\Stream;

/**
 * @method TransportRound|null find($id, $lockMode = null, $lockVersion = null)
 * @method TransportRound|null findOneBy(array $criteria, array $orderBy = null)
 * @method TransportRound[]    findAll()
 * @method TransportRound[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TransportRoundRepository extends EntityRepository {

    #[ArrayShape(["data" => "mixed", "count" => "int", "total" => "int"])]
    public function findByParamAndFilters(InputBag $params, $filters): array {
        $qb = $this->createQueryBuilder("transport_round");

        $total = QueryCounter::count($qb, "transport_round");

        if($params->get("dateMin")) {
            $date = DateTime::createFromFormat("d/m/Y", $params->get("dateMin"));
            $date = $date->format("Y-m-d");

            $qb->andWhere('delivery.expectedAt >= :datetimeMin OR collect.expectedAt >= :dateMin')
                ->setParameter('datetimeMin', "$date 00:00:00")
                ->setParameter('dateMin', $date);
        }

        if($params->get("dateMax")) {
            $date = DateTime::createFromFormat("d/m/Y", $params->get("dateMax"));
            $date = $date->format("Y-m-d");

            $qb->andWhere('delivery.expectedAt <= :datetimeMax OR collect.expectedAt <= :dateMax')
                ->setParameter('datetimeMax', "$date 23:59:59")
                ->setParameter('dateMax', $date);
        }

        // filtres sup
        /*foreach ($filters as $filter) {
            switch ($filter['field']) {
            }
        }*/

        // compte éléments filtrés
        $countFiltered = QueryCounter::count($qb, "transport_round");

        if ($params->getInt('start')) {
            $qb->setFirstResult($params->getInt('start'));
        }
        if ($params->getInt('length')) {
            $qb->setMaxResults($params->getInt('length'));
        }

        $qb->orderBy("transport_round.beganAt", "DESC");

        return [
            "data" => $qb->getQuery()->getResult(),
            "count" => $countFiltered,
            "total" => $total,
        ];
    }
    public function getForSelect(?string $term): array {

        $query = $this->createQueryBuilder("transport_round");

        return $query->select("transport_round.id AS id, transport_round.number AS text")
            ->join('transport_round.status', 'round_status')
            ->andWhere('round_status.code like :awaitingDelivererStatus')
            ->andWhere("transport_round.number LIKE :term")

            ->setParameter("term", "%$term%")
            ->setParameter("awaitingDelivererStatus", TransportRound::STATUS_AWAITING_DELIVERER)
            ->getQuery()
            ->getArrayResult();
    }
}
