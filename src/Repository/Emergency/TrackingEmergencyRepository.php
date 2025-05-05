<?php

namespace App\Repository\Emergency;


use App\Entity\Emergency\Emergency;
use App\Entity\Arrivage;
use App\Entity\Emergency\TrackingEmergency;
use App\Entity\Fournisseur;
use DateTime;
use Doctrine\ORM\EntityRepository;
use WiiCommon\Helper\Stream;

/**
 * @extends EntityRepository<TrackingEmergencyRepository>
 */
class TrackingEmergencyRepository extends EntityRepository {

    public function countMatching(Emergency    $emergency,
                                  DateTime     $dateStart,
                                  DateTime     $dateEnd,
                                  ?Fournisseur $supplier,
                                  ?string      $orderNumber,
                                  ?string      $postNumber): int {

        $queryBuilder = $this->createQueryBuilder('emergency');

        $exprBuilder = $queryBuilder->expr();
        $queryBuilder
            ->select('COUNT(emergency)')
            ->andWhere($exprBuilder->orX(
                ':dateStart BETWEEN emergency.dateStart AND emergency.dateEnd',
                ':dateEnd BETWEEN emergency.dateStart AND emergency.dateEnd',
                'emergency.dateStart BETWEEN :dateStart AND :dateEnd',
                'emergency.dateEnd BETWEEN :dateStart AND :dateEnd'
            ))
            ->andWhere('emergency.supplier = :supplier')
            ->andWhere('emergency.orderNumber = :orderNumber')
            ->andWhere('emergency.id != :emergencyId')
            ->setParameter('dateStart', $dateStart)
            ->setParameter('dateEnd', $dateEnd)
            ->setParameter('emergencyId', $emergency->getId())
            ->setParameter('supplier', $supplier)
            ->setParameter('orderNumber', $orderNumber);

        if (!empty($postNumber)) {
            $queryBuilder
                ->andWhere('emergency.postNumber = :postNumber')
                ->setParameter('postNumber', $postNumber);
        }

        return $queryBuilder
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return array<TrackingEmergency>
     */
    public function findMatchingEmergencies(Arrivage $arrival,
                                            array    $fields,
                                            ?string  $orderNumber,
                                            ?string  $postNumber,
                                                     $excludeTriggered = false): array {
        $queryBuilder = $this->createQueryBuilder('emergency')
            ->where(':date BETWEEN emergency.dateStart AND emergency.dateEnd')
            ->setParameter('date', $arrival->getDate());

        $values = [
            'supplier' => $arrival->getFournisseur(),
            'carrier' => $arrival->getTransporteur(),
            'orderNumber' => $orderNumber
                ? Stream::explode(',', $orderNumber)->filter()->values()
                : null,
        ];

        foreach ($fields as $field) {
            if(!empty($values[$field])) {
                $queryBuilder
                    ->andWhere("emergency.$field IN (:$field)")
                    ->setParameter("$field", $values[$field]);
            }
        }

        if (!empty($postNumber)) {
            $queryBuilder
                ->andWhere('emergency.postNumber = :postNumber')
                ->setParameter('postNumber', $postNumber);
        }

        if ($excludeTriggered) {
            $queryBuilder->andWhere('emergency.closedAt IS NULL');
        }

        return $queryBuilder
            ->getQuery()
            ->getResult();
    }
}
