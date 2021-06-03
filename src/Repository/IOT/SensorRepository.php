<?php

namespace App\Repository\IOT;

use App\Entity\IOT\Sensor;
use Doctrine\ORM\EntityRepository;

/**
 * @method Sensor|null find($id, $lockMode = null, $lockVersion = null)
 * @method Sensor|null findOneBy(array $criteria, array $orderBy = null)
 * @method Sensor[]    findAll()
 * @method Sensor[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class SensorRepository extends EntityRepository {
    public function getSensorByCode(string $code, bool $onlyAvailable = false): array {
        $queryBuilder = $this->createQueryBuilder('sensor');

        $queryBuilder
            ->select('sensor.id AS id')
            ->addSelect('sensor.code AS code')
            ->addSelect('sensor.code AS text')
            ->addSelect('sensor.type AS type')
            ->addSelect('join_profile.name AS profile')
            ->addSelect('sensor.frequency AS frequency')
            ->join('sensor.profile', 'join_profile')
            ->andWhere('sensor.code LIKE :search')
            ->setParameter('search', '%' . str_replace('_', '\_', $code) . '%');

        if ($onlyAvailable) {
            $queryBuilder
                ->leftJoin('sensor.sensorWrappers', 'join_sensorWrapper')
                ->andWhere('join_sensorWrapper.id IS NULL OR join_sensorWrapper.deleted = true')
                ->distinct();
        }

        return $queryBuilder
            ->getQuery()
            ->getResult();
    }

}
