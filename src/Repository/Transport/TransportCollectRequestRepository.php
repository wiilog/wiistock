<?php

namespace App\Repository\Transport;

use App\Entity\Transport\TransportCollectRequest;
use DateTime;
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
    public function findByFileNumber(DateTime $dateTime, string $fileNumber): array {
        return $this->createQueryBuilder('request')
            ->join('request.contact', 'contact')
            ->andWhere('contact.fileNumber = :fileNumber')
            ->andWhere('request.expectedAt = :expectedAt')
            ->setParameter('fileNumber', $fileNumber)
            ->setParameter('expectedAt', $dateTime->format('Y-m-d'))
            ->getQuery()
            ->getResult();
    }
}
