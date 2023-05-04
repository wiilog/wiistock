<?php

namespace App\Repository\ShippingRequest;

use Doctrine\ORM\EntityRepository;

class ShippingRequestExpectedLineRepository extends EntityRepository {
    public function iterateShippingRequestExpectedLines(): iterable {
        return $this
            ->createQueryBuilder('shipping_request_expected_line')
            ->getQuery()
            ->toIterable();
    }
}
