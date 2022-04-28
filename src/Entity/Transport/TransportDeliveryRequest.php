<?php

namespace App\Entity\Transport;

use App\Repository\Transport\TransportDeliveryRequestRepository;
use DateTime;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TransportDeliveryRequestRepository::class)]
class TransportDeliveryRequest extends TransportRequest {

    #[ORM\Column(type: 'datetime', nullable: true)]
    protected ?DateTime $expectedAt = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $emergency = null;

    #[ORM\OneToOne(inversedBy: 'delivery', targetEntity: TransportCollectRequest::class, cascade: ['persist', 'remove'])]
    private ?TransportCollectRequest $collect = null;

    public function __construct() {
        parent::__construct();
    }

    public function getExpectedAt(): ?DateTime {
        return $this->expectedAt;
    }

    public function setExpectedAt(DateTime $expectedAt): self {
        $this->expectedAt = $expectedAt;
        return $this;
    }

    public function getEmergency(): ?string
    {
        return $this->emergency;
    }

    public function setEmergency(?string $emergency): self {
        $this->emergency = $emergency;

        return $this;
    }

    public function getCollect(): ?TransportCollectRequest {
        return $this->collect;
    }

    public function setCollect(?TransportCollectRequest $collect): self {
        if($this->collect && $this->collect->getDelivery() !== $this) {
            $oldCollect = $this->collect;
            $this->collect = null;
            $oldCollect->setDelivery(null);
        }
        $this->collect = $collect;
        if($this->collect && $this->collect->getDelivery() !== $this) {
            $this->collect->setDelivery($this);
        }

        return $this;
    }

    public function canBeDeleted(): bool {
        return (
            !$this->isInRound()
            && in_array($this->getStatus()?->getCode(), [
                TransportRequest::STATUS_TO_DELIVER,
                TransportRequest::STATUS_TO_PREPARE,
                TransportRequest::STATUS_AWAITING_VALIDATION,
                TransportRequest::STATUS_SUBCONTRACTED,
            ])
        );
    }

    public function canBeUpdated(): bool {
        return in_array($this->getStatus()?->getCode(), [
            TransportRequest::STATUS_AWAITING_VALIDATION,
            TransportRequest::STATUS_AWAITING_PLANNING,
            TransportRequest::STATUS_TO_COLLECT,
        ]);
    }

    public function canBeCancelled(): bool {
        return $this->isInRound();
    }

}
