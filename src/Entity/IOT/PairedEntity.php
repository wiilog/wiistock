<?php


namespace App\Entity\IOT;


use App\Entity\IOT\SensorMessage as SensorMessage;
use Doctrine\Common\Collections\Collection;

interface PairedEntity {
    public function getPairings(): Collection;
    public function getActivePairing(): ?Pairing;
    public function addPairing(Pairing $pairing): self;
    public function removePairing(Pairing $pairing): self;

    public function getSensorMessages(): Collection;
    public function getSensorMessagesBetween($start, $end, string $type = null): array;
    public function addSensorMessage(SensorMessage $sensorMessage): self;
    public function removeSensorMessage(SensorMessage $sensorMessage): self;
    public function setSensorMessage($sensorMessages): self;
    public function clearSensorMessages(): self;
    public function removeSensorMessages(array $excludeIds): self;
}
