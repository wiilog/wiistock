<?php

namespace App\Repository;

use App\Entity\PurchaseRequest;
use Doctrine\ORM\EntityRepository;

/**
 * @method PurchaseRequest|null find($id, $lockMode = null, $lockVersion = null)
 * @method PurchaseRequest|null findOneBy(array $criteria, array $orderBy = null)
 * @method PurchaseRequest[]    findAll()
 * @method PurchaseRequest[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PurchaseRequestRepository extends EntityRepository
{
    public function findByState(int $state) {
        return $this->createQueryBuilder('purchase_request')
            ->join('purchase_request.status', 'status')
            ->where('status.state = :state')
            ->setParameter('state', $state)
            ->getQuery()
            ->execute();
    }
}
