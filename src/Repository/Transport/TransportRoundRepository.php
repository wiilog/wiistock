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
            ->andWhere('status.code IN (:availableStatuses)')
            ->andWhere('transport_round.noDeliveryToReturn = 0 OR transport_round.noCollectToReturn = 0')
            ->join('transport_round.status', 'status')
            ->orderBy('transport_round.expectedAt', 'ASC')
            ->setParameter('user', $user)
            ->setParameter('availableStatuses', [
                TransportRound::STATUS_AWAITING_DELIVERER,
                TransportRound::STATUS_ONGOING,
                TransportRound::STATUS_FINISHED
            ])
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
        $qb = $this->createQueryBuilder('transport_round')
            ->setParameter('dateMin', $dateMin)
            ->setParameter('dateMax', $dateMax)
            ->where('transport_round.expectedAt BETWEEN :dateMin AND :dateMax');
        return $qb
            ->getQuery()
            ->toIterable();
    }

    public function iterateFinishedTransportRounds(): iterable {
        $qb = $this->createQueryBuilder('transport_round')
            ->join('transport_round.status', 'status')
            ->where('status.code IN (:finishedStatuses)')
            ->setParameter('finishedStatuses', [TransportRound::STATUS_FINISHED]);
        return $qb
            ->getQuery()
            ->toIterable();
    }

    public function iterateTodayFinishedTransportRounds(): iterable {
        $now = new DateTime('now');
        $beginDayDate = clone $now;
        $beginDayDate->setTime(0, 0, 0);
        $endDayDate = clone $now;
        $endDayDate->setTime(23, 59, 59);

        $qb = $this->createQueryBuilder('transport_round')
            ->andWhere('transport_round.beganAt IS NOT NULL')
            ->andWhere('transport_round.beganAt <= :dateMax')
            ->andWhere('(transport_round.endedAt IS NULL OR transport_round.endedAt BETWEEN :dateMin AND :dateMax)')
            ->setParameter('dateMin', $beginDayDate)
            ->setParameter('dateMax', $endDayDate);

        return $qb
            ->getQuery()
            ->toIterable();
    }

    public function findTodayRounds($deliverer) {
        $now = new DateTime();

        return $this->createQueryBuilder("round")
            ->join("round.status", "status")
            ->andWhere("round.deliverer = :deliverer")
            ->andWhere("DATE_FORMAT(round.expectedAt, '%Y-%m-%d') = DATE_FORMAT(:now, '%Y-%m-%d')")
            ->andWhere("status.code != :finished")
            ->setParameter("deliverer", $deliverer)
            ->setParameter("now", $now)
            ->setParameter("finished", TransportRound::STATUS_FINISHED)
            ->getQuery()
            ->getResult();
    }
}
