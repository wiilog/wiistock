<?php

namespace App\Entity\Transport;

use App\Repository\Transport\TransportCollectRequestRepository;
use DateTime;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TransportCollectRequestRepository::class)]
class TransportCollectRequest extends TransportRequest {

    #[ORM\Column(type: 'date', nullable: true)]
    protected ?DateTime $expectedAt = null;

    #[ORM\ManyToOne(targetEntity: CollectTimeSlot::class, inversedBy: 'transportCollectRequests')]
    private ?CollectTimeSlot $timeSlot = null;

    #[ORM\OneToOne(mappedBy: 'collect', targetEntity: TransportDeliveryRequest::class, cascade: ['persist', 'remove'])]
    private ?TransportDeliveryRequest $delivery = null;

    public function getTimeSlot(): ?CollectTimeSlot {
        return $this->timeSlot;
    }

    public function setTimeSlot(?CollectTimeSlot $timeSlot): self {
        if ($this->timeSlot && $this->timeSlot !== $timeSlot) {
            $this->timeSlot->removeTransportCollectRequest($this);
        }
        $this->timeSlot = $timeSlot;
        $timeSlot?->addTransportCollectRequest($this);

        return $this;
    }

    public function getDelivery(): ?TransportDeliveryRequest {
        return $this->delivery;
    }

    public function setDelivery(?TransportDeliveryRequest $delivery): self {
        if($this->delivery && $this->delivery->getCollect() !== $this) {
            $oldDelivery = $this->delivery;
            $this->delivery = null;
            $oldDelivery->setCollect(null);
        }
        $this->delivery = $delivery;
        if($this->delivery && $this->delivery->getCollect() !== $this) {
            $this->delivery->setCollect($this);
        }

        return $this;
    }

    public function canBeDeleted(): bool {
        return !$this->isInRound();
    }

    public function canBeUpdated(): bool {
        return in_array($this->getStatus()?->getCode(), [
            TransportRequest::STATUS_AWAITING_VALIDATION,
            TransportRequest::STATUS_AWAITING_PLANNING,
            TransportRequest::STATUS_TO_COLLECT,
        ]);
    }

    public function canBeCancelled(): bool {
        return $this->isInRound()
            && $this->getStatus()->getCode() !== TransportRequest::STATUS_CANCELLED
            && $this->getStatus()->getCode() !== TransportRequest::STATUS_NOT_COLLECTED
            && $this->getStatus()->getCode() !== TransportRequest::STATUS_FINISHED ;
    }

    public function getExpectedAt(): ?DateTime {
        return $this->expectedAt;
    }

    public function setExpectedAt(DateTime $expectedAt): self {
        $this->expectedAt = $expectedAt;
        return $this;
    }

    public function isNotCollected(): bool {
        return $this->getStatus()->getCode() === self::STATUS_NOT_COLLECTED;
    }

}
