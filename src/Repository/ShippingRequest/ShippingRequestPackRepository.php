<?php

namespace App\Repository\ShippingRequest;

use Doctrine\ORM\EntityRepository;

class ShippingRequestPackRepository extends EntityRepository {
    public function iterateShippingRequestExpectedLines(): iterable {
        return $this
            ->createQueryBuilder('shipping_request_pack')
            ->getQuery()
            ->toIterable();
    }
}
