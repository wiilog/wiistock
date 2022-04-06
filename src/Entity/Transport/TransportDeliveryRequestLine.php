<?php

namespace App\Entity\Transport;

use App\Entity\Nature;
use App\Repository\Transport\TransportDeliveryRequestLineRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TransportDeliveryRequestLineRepository::class)]
class TransportDeliveryRequestLine extends TransportRequestLine {

    #[ORM\ManyToOne(targetEntity: TemperatureRange::class, inversedBy: 'transportDeliveryRequestNatures')]
    private ?TemperatureRange $temperatureRange = null;

    public function getTemperatureRange(): ?TemperatureRange {
        return $this->temperatureRange;
    }

    public function setTemperatureRange(?TemperatureRange $temperatureRange): self {
        if($this->temperatureRange && $this->temperatureRange !== $temperatureRange) {
            $this->temperatureRange->removeTransportDeliveryRequestNature($this);
        }
        $this->temperatureRange = $temperatureRange;
        $temperatureRange?->addTransportDeliveryRequestNature($this);

        return $this;
    }
}
