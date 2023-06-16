<?php

namespace App\Repository\DeliveryRequest;

use App\Entity\DeliveryRequest\Demande;
use App\Entity\Pack;
use Doctrine\ORM\EntityRepository;
use WiiCommon\Helper\Stream;

class DeliveryRequestArticleLineRepository extends EntityRepository
{

    public function findByRequests(array $requests): array {
        $result = $this->createQueryBuilder('line')
            ->select('line')
            ->addSelect('request.id AS requestId')
            ->join('line.request', 'request')
            ->where('request IN (:requests)')
            ->setParameter('requests', $requests)
            ->getQuery()
            ->execute();

        return Stream::from($result)
            ->keymap(fn(array $current) => [$current['requestId'], $current[0]], true)
            ->toArray();
    }

    public function isOngoingAndUsingPack(Pack|int $pack): bool {
        return $this->createQueryBuilder("line")
            ->select("COUNT(line)")
            ->join("line.request", "request")
            ->join("request.statut", "status")
            ->andWhere("line.pack = :pack")
            ->andWhere("status.code NOT IN (:statuses)")
            ->setParameter("pack", $pack)
            ->setParameter("statuses", [Demande::STATUT_LIVRE, Demande::STATUT_LIVRE_INCOMPLETE])
            ->getQuery()
            ->getSingleScalarResult() > 0;
    }

}
