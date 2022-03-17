<?php

namespace App\Entity\Transport;

use App\Entity\Nature;
use App\Repository\Transport\TransportDeliveryRequestNatureRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TransportDeliveryRequestNatureRepository::class)]
class TransportDeliveryRequestNature
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Nature::class, inversedBy: 'transportDeliveryRequestNatures')]
    private ?Nature $nature = null;

    #[ORM\ManyToOne(targetEntity: TransportDeliveryRequest::class, inversedBy: 'transportDeliveryRequestNatures')]
    private ?TransportDeliveryRequest $transportDeliveryRequest = null;

    #[ORM\ManyToOne(targetEntity: TemperatureRange::class, inversedBy: 'transportDeliveryRequestNatures')]
    private ?TemperatureRange $temperatureRange = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNature(): ?Nature
    {
        return $this->nature;
    }

    public function setNature(?Nature $nature): self {
        if($this->nature && $this->nature !== $nature) {
            $this->nature->removeTransportDeliveryRequestNature($this);
        }
        $this->nature = $nature;
        $nature?->addTransportDeliveryRequestNature($this);

        return $this;
    }

    public function getTransportDeliveryRequest(): ?TransportDeliveryRequest
    {
        return $this->transportDeliveryRequest;
    }

    public function setTransportDeliveryRequest(?TransportDeliveryRequest $transportDeliveryRequest): self {
        if($this->transportDeliveryRequest && $this->transportDeliveryRequest !== $transportDeliveryRequest) {
            $this->transportDeliveryRequest->removeTransportDeliveryRequestNature($this);
        }
        $this->transportDeliveryRequest = $transportDeliveryRequest;
        $transportDeliveryRequest?->addTransportDeliveryRequestNature($this);

        return $this;
    }

    public function getTemperatureRange(): ?TemperatureRange
    {
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
