<?php

namespace App\Repository\DeliveryRequest;

use Doctrine\ORM\EntityRepository;
use WiiCommon\Helper\Stream;

class DeliveryRequestArticleLineRepository extends EntityRepository {

    public function findByRequests(array $requests): array
    {
        $result = $this->createQueryBuilder('line')
            ->select('line')
            ->addSelect('request.id AS requestId')
            ->join('line.request' , 'request')
            ->where('request IN (:requests)')
            ->setParameter('requests', $requests)
            ->getQuery()
            ->execute();

        return Stream::from($result)
            ->keymap(fn(array $current) => [$current['requestId'], $current[0]], true)
            ->toArray();
    }
}
