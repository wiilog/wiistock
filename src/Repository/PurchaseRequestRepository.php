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

    public function getLastNumberByDate(string $date): ?string {
        $result = $this->createQueryBuilder('purchase_request')
            ->select('purchase_request.number')
            ->where('purchase_request.number LIKE :value')
            ->orderBy('purchase_request.creationDate', 'DESC')
            ->addOrderBy('purchase_request.number', 'DESC')
            ->setParameter('value', PurchaseRequest::NUMBER_PREFIX . '-' . $date . '%')
            ->getQuery()
            ->execute();
        return $result ? $result[0]['number'] : null;
    }
}
