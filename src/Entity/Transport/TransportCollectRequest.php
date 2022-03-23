<?php

namespace App\Entity\Transport;

use App\Repository\Transport\TransportCollectRequestRepository;
use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TransportCollectRequestRepository::class)]
class TransportCollectRequest extends TransportRequest
{

    #[ORM\Column(type: 'datetime')]
    private ?DateTime $expectedAt = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?DateTime $validationDate = null;

    #[ORM\ManyToOne(targetEntity: CollectTimeSlot::class, inversedBy: 'transportCollectRequests')]
    private ?CollectTimeSlot $timeSlot = null;

    #[ORM\OneToMany(mappedBy: 'transportCollectRequest', targetEntity: TransportCollectRequestNature::class)]
    private Collection $transportCollectRequestNatures;

    #[ORM\OneToOne(targetEntity: TransportDeliveryRequest::class, cascade: ['persist', 'remove'])]
    private ?TransportDeliveryRequest $transportDeliveryRequest = null;

    public function __construct()
    {
        parent::__construct();
        $this->transportCollectRequestNatures = new ArrayCollection();
    }

    public function getExpectedAt(): ?DateTime
    {
        return $this->expectedAt;
    }

    public function setExpectedAt(DateTime $expectedAt): self
    {
        $this->expectedAt = $expectedAt;

        return $this;
    }

    public function getValidationDate(): ?DateTime
    {
        return $this->validationDate;
    }

    public function setValidationDate(?DateTime $validationDate): self
    {
        $this->validationDate = $validationDate;

        return $this;
    }

    public function getTimeSlot(): ?CollectTimeSlot
    {
        return $this->timeSlot;
    }

    public function setTimeSlot(?CollectTimeSlot $timeSlot): self {
        if($this->timeSlot && $this->timeSlot !== $timeSlot) {
            $this->timeSlot->removeTransportCollectRequest($this);
        }
        $this->timeSlot = $timeSlot;
        $timeSlot?->addTransportCollectRequest($this);

        return $this;
    }

    /**
     * @return Collection<int, TransportCollectRequestNature>
     */
    public function getTransportCollectRequestNatures(): Collection
    {
        return $this->transportCollectRequestNatures;
    }

    public function addTransportCollectRequestNature(TransportCollectRequestNature $transportCollectRequestNature): self
    {
        if (!$this->transportCollectRequestNatures->contains($transportCollectRequestNature)) {
            $this->transportCollectRequestNatures[] = $transportCollectRequestNature;
            $transportCollectRequestNature->setTransportCollectRequest($this);
        }

        return $this;
    }

    public function removeTransportCollectRequestNature(TransportCollectRequestNature $transportCollectRequestNature): self
    {
        if ($this->transportCollectRequestNatures->removeElement($transportCollectRequestNature)) {
            // set the owning side to null (unless already changed)
            if ($transportCollectRequestNature->getTransportCollectRequest() === $this) {
                $transportCollectRequestNature->setTransportCollectRequest(null);
            }
        }

        return $this;
    }

    public function getTransportDeliveryRequest(): ?TransportDeliveryRequest
    {
        return $this->transportDeliveryRequest;
    }

    public function setTransportDeliveryRequest(?TransportDeliveryRequest $transportDeliveryRequest): self
    {
        $this->transportDeliveryRequest = $transportDeliveryRequest;

        return $this;
    }
}
