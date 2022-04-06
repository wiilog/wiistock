<?php

namespace App\Entity\Transport;

use App\Repository\Transport\TransportCollectRequestRepository;
use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TransportCollectRequestRepository::class)]
class TransportCollectRequest extends TransportRequest {

    #[ORM\Column(type: 'date', nullable: true)]
    protected ?DateTime $expectedAt = null;

    #[ORM\ManyToOne(targetEntity: CollectTimeSlot::class, inversedBy: 'transportCollectRequests')]
    private ?CollectTimeSlot $timeSlot = null;

    #[ORM\OneToMany(mappedBy: 'transportCollectRequest', targetEntity: TransportCollectRequestNature::class, cascade: ['remove'])]
    private Collection $transportCollectRequestNatures;

    #[ORM\OneToOne(mappedBy: 'collect', targetEntity: TransportDeliveryRequest::class, cascade: ['persist', 'remove'])]
    private ?TransportDeliveryRequest $delivery = null;

    public function __construct() {
        parent::__construct();
        $this->transportCollectRequestNatures = new ArrayCollection();
    }

    public function getTimeSlot(): ?CollectTimeSlot
    {
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

    /**
     * @return Collection<int, TransportCollectRequestNature>
     */
    public function getTransportCollectRequestNatures(): Collection {
        return $this->transportCollectRequestNatures;
    }

    public function addTransportCollectRequestNature(TransportCollectRequestNature $transportCollectRequestNature): self {
        if (!$this->transportCollectRequestNatures->contains($transportCollectRequestNature)) {
            $this->transportCollectRequestNatures[] = $transportCollectRequestNature;
            $transportCollectRequestNature->setTransportCollectRequest($this);
        }

        return $this;
    }

    public function removeTransportCollectRequestNature(TransportCollectRequestNature $transportCollectRequestNature): self {
        if ($this->transportCollectRequestNatures->removeElement($transportCollectRequestNature)) {
            // set the owning side to null (unless already changed)
            if ($transportCollectRequestNature->getTransportCollectRequest() === $this) {
                $transportCollectRequestNature->setTransportCollectRequest(null);
            }
        }

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

}
