<?php

namespace App\Repository\ShippingRequest;

use Doctrine\ORM\EntityRepository;

class ShippingRequestLineRepository extends EntityRepository {
    public function iterateShippingRequestLines(): iterable {
        return $this
            ->createQueryBuilder('shipping_request_line')
            ->getQuery()
            ->toIterable();
    }
}
