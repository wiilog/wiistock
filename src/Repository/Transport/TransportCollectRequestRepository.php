<?php

namespace App\Repository\Transport;

use App\Entity\Transport\TransportCollectRequest;
use App\Entity\Transport\TransportRequest;
use Doctrine\ORM\EntityRepository;

/**
 * @method TransportCollectRequest|null find($id, $lockMode = null, $lockVersion = null)
 * @method TransportCollectRequest|null findOneBy(array $criteria, array $orderBy = null)
 * @method TransportCollectRequest[]    findAll()
 * @method TransportCollectRequest[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TransportCollectRequestRepository extends EntityRepository {

    /**
     * @return array<TransportCollectRequest>
     */
    public function findOngoingByFileNumber(string $fileNumber): array {
        return $this->createQueryBuilder('request')
            ->join('request.contact', 'contact')
            ->join('request.status', 'status')
            ->andWhere('contact.fileNumber = :fileNumber')
            ->andWhere('status.code IN (:ongoing_status)')
            ->setParameter('fileNumber', $fileNumber)
            ->setParameter('ongoing_status', [
                TransportRequest::STATUS_AWAITING_VALIDATION,
                TransportRequest::STATUS_AWAITING_PLANNING,
                TransportRequest::STATUS_TO_COLLECT,
                TransportRequest::STATUS_ONGOING,
            ])
            ->getQuery()
            ->getResult();
    }
}
