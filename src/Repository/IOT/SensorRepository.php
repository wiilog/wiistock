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

    public function getForSelect(string $term, bool $onlyAvailable = false) {
        $qb = $this->createQueryBuilder("sensor");

        if ($onlyAvailable) {
            $qb->leftJoin("sensor.sensorWrappers", "join_sensor_wrapper")
                ->andWhere("join_sensor_wrapper.id IS NULL OR join_sensor_wrapper.deleted = true")
                ->distinct();
        }

        return $qb->select("sensor.id AS id")
            ->addSelect("sensor.code AS code")
            ->addSelect("sensor.code AS text")
            ->addSelect("join_type.label AS typeLabel")
            ->addSelect("join_type.id AS typeId")
            ->addSelect("join_profile.name AS profile")
            ->addSelect("sensor.frequency AS frequency")
            ->join("sensor.profile", "join_profile")
            ->join("sensor.type", "join_type")
            ->where("sensor.code LIKE :term")
            ->setParameter("term", "%" . str_replace("_", "\_", $term) . "%")
            ->getQuery()
            ->getArrayResult();
    }
}
