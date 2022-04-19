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
        return (
            !$this->isInRound()
            && in_array($this->getStatus()?->getCode(), [
                TransportRequest::STATUS_TO_COLLECT,
                TransportRequest::STATUS_AWAITING_PLANNING,
                TransportRequest::STATUS_AWAITING_VALIDATION,
            ])
        );
    }

    public function canBeCancelled(): bool {
        return (
            $this->isInRound()
            && in_array($this->getStatus()?->getCode(), [
                TransportRequest::STATUS_TO_COLLECT,
                TransportRequest::STATUS_ONGOING,
                TransportRequest::STATUS_AWAITING_PLANNING,
            ])
        );
    }

    public function canBeUpdated(): bool {
        return in_array($this->getStatus()?->getCode(), [
            TransportRequest::STATUS_AWAITING_VALIDATION,
            TransportRequest::STATUS_TO_PREPARE,
            TransportRequest::STATUS_TO_DELIVER,
            TransportRequest::STATUS_SUBCONTRACTED,
        ]);
    }

}
