<?php

namespace App\Repository\Emergency;


use App\Entity\Fournisseur;
use DateTime;
use Doctrine\ORM\EntityRepository;

/**
 * @extends EntityRepository<TrackingEmergencyRepository>
 */
class TrackingEmergencyRepository extends EntityRepository {

    public function countMatching(DateTime     $dateStart,
                                  DateTime     $dateEnd,
                                  ?Fournisseur $supplier,
                                  ?string      $command,
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
            ->andWhere('emergency.command = :command')
            ->setParameter('dateStart', $dateStart)
            ->setParameter('dateEnd', $dateEnd)
            ->setParameter('supplier', $supplier)
            ->setParameter('command', $command);

        if (!empty($postNumber)) {
            $queryBuilder
                ->andWhere('emergency.postNumber = :postNumber')
                ->setParameter('postNumber', $postNumber);
        }

        return $queryBuilder
            ->getQuery()
            ->getSingleScalarResult();
    }
}
