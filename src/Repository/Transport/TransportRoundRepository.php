<?php

namespace App\Repository\Transport;

use App\Entity\FiltreSup;
use App\Entity\Transport\TransportRound;
use App\Entity\Utilisateur;
use App\Helper\QueryCounter;
use DateTime;
use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\EntityRepository;
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

            $qb->andWhere('transport_round.expectedAt >= :datetimeMin')
                ->setParameter('datetimeMin', "$date 00:00:00");
        }

        if($params->get("dateMax")) {
            $date = DateTime::createFromFormat("d/m/Y", $params->get("dateMax"));
            $date = $date->format("Y-m-d");

            $qb->andWhere('transport_round.expectedAt <= :datetimeMax')
                ->setParameter('datetimeMax', "$date 23:59:59");
        }

        // filtres sup
        foreach ($filters as $filter) {
            switch ($filter['field']) {
                case FiltreSup::FIELD_STATUT:
                    $value = Stream::explode(",", $filter['value'])
                        ->map(fn($line) => explode(":", $line))
                        ->toArray();

                    $qb
                        ->join('transport_round.status', 'filter_status')
                        ->andWhere('filter_status.nom IN (:status)')
                        ->setParameter('status', $value);
                    break;
                case FiltreSup::FIELD_ROUND_NUMBER:
                    $qb->andWhere("transport_round.number LIKE :filter_round_number")
                        ->setParameter("filter_round_number", "%" . $filter['value'] . "%");
                    break;
                case FiltreSup::FIELD_DELIVERERS:
                    $value = Stream::explode(",", $filter['value'])
                        ->map(fn($line) => explode(":", $line))
                        ->toArray();

                    $qb
                        ->join('transport_round.deliverer', 'filter_deliverer')
                        ->andWhere('filter_deliverer.id in (:filter_deliverer_values)')
                        ->setParameter('filter_deliverer_values', $value);
                    break;
            }
        }

        // compte éléments filtrés
        $countFiltered = QueryCounter::count($qb, "transport_round");

        if ($params->getInt('start')) {
            $qb->setFirstResult($params->getInt('start'));
        }
        if ($params->getInt('length')) {
            $qb->setMaxResults($params->getInt('length'));
        }

        $qb->orderBy("transport_round.expectedAt", "DESC");

        return [
            "data" => $qb->getQuery()->getResult(),
            "count" => $countFiltered,
            "total" => $total,
        ];
    }

    public function findMobileTransportRoundsByUser(Utilisateur $user): array {
        return $this->createQueryBuilder('transport_round')
            ->andWhere('transport_round.deliverer = :user')
            ->andWhere('transport_round.endedAt IS NULL')
            ->orderBy('transport_round.expectedAt', 'ASC')
            ->setParameter('user', $user)
            ->getQuery()
            ->getResult();
    }

    public function getForSelect(?string $term): array {
        $query = $this->createQueryBuilder("transport_round");
        $now = (new DateTime('now'))->setTime(0, 0);

        return $query->select("transport_round.id AS id, CONCAT(:roundPrefix, transport_round.number) AS text")
            ->join('transport_round.status', 'round_status')
            ->andWhere('round_status.code like :awaitingDelivererStatus')
            ->andWhere("transport_round.number LIKE :term")
            ->andWhere("transport_round.expectedAt >= :now")
            ->setParameter("term", "%$term%")
            ->setParameter("awaitingDelivererStatus", TransportRound::STATUS_AWAITING_DELIVERER)
            ->setParameter("roundPrefix", TransportRound::NUMBER_PREFIX)
            ->setParameter("now", $now)
            ->getQuery()
            ->getArrayResult();
    }

    public function getLastNumberByDate(string $date): ?string {
        $result = $this->createQueryBuilder('request')
            ->select('request.number')
            ->where('request.number LIKE :value')
            ->orderBy('request.createdAt', Criteria::DESC)
            ->addOrderBy('request.number', Criteria::DESC)
            ->setParameter('value', $date . '%')
            ->getQuery()
            ->execute();
        return $result ? $result[0]['number'] : null;
    }

    public function iterateTransportRoundsByDates(DateTime $dateMin, DateTime $dateMax): iterable {
        $dateMin = $dateMin->format("Y-m-d");
        $dateMax = $dateMax->format("Y-m-d");
        $qb = $this->createQueryBuilder('transport_round')
            ->setParameter('dateMin' , "$dateMin 00:00:00")
            ->setParameter('dateMax' , "$dateMax 23:59:59")
            ->where('transport_round.expectedAt BETWEEN :dateMin AND :dateMax')
            ->andWhere('transport_round.id IS NOT NULL');
        return $qb
            ->getQuery()
            ->toIterable();
    }

    public function iterateTransportRoundsFinished(): iterable {

        $qb = $this->createQueryBuilder('transport_round')
            ->where('transport_round.endedAt IS NOT NULL')
            ->andWhere('transport_round.id IS NOT NULL');
        return $qb
            ->getQuery()
            ->toIterable();
    }
}
