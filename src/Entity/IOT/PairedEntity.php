<?php


namespace App\Entity\IOT;


use App\Entity\IOT\SensorMessage as SensorMessage;
use Doctrine\Common\Collections\Collection;

interface PairedEntity {

    public function getPairings(): Collection;

    public function getActivePairing(): ?Pairing;

    public function getSensorMessages(): Collection;

    /**
     * @return SensorMessage[]
     */
    public function getSensorMessagesBetween($start, $end, ?array $options = []): array;

    public function getLastMessage(): ?SensorMessage;

}
