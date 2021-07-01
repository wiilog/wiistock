<?php

namespace App\Service;

use App\Entity\LocationCluster;
use App\Entity\LocationClusterMeter;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;

class LocationClusterService {

    public const METER_ACTION_INCREASE = 1;
    public const METER_ACTION_DECREASE = 2;

    public function setMeter(EntityManagerInterface $entityManager,
                             int $action,
                             DateTime $date,
                             LocationCluster $locationClusterInto,
                             LocationCluster $locationClusterFrom = null) {
        $locationClusterMeterRepository = $entityManager->getRepository(LocationClusterMeter::class);

        // TODO check : working ? use $date->format('Y-m-d') ?
        $meter = $locationClusterMeterRepository->findOneBy([
            'date' => $date,
            'locationClusterFrom' => $locationClusterFrom,
            'locationClusterInto' => $locationClusterInto
        ]);

        if (!isset($meter)) {
            $meter = new LocationClusterMeter();
            $meter
                ->setDate($date)
                ->setLocationClusterFrom($locationClusterFrom)
                ->setLocationClusterInto($locationClusterInto);
            $entityManager->persist($meter);
        }

        switch ($action) {
            case self::METER_ACTION_INCREASE:
                $meter->increaseDropCounter();
                break;
            case self::METER_ACTION_DECREASE:
                $meter->decreaseDropCounter();
                break;
            default:
                break;
        }
    }
}
