<?php

namespace App\Entity\Transport;

use App\Repository\Transport\TransportDeliveryRequestRepository;
use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TransportDeliveryRequestRepository::class)]
class TransportDeliveryRequest extends TransportRequest {

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $emergency = null;

    #[ORM\OneToMany(mappedBy: 'transportDeliveryRequest', targetEntity: TransportDeliveryRequestNature::class)]
    private Collection $transportDeliveryRequestNatures;

    #[ORM\OneToOne(inversedBy: 'delivery', targetEntity: TransportCollectRequest::class, cascade: ['persist', 'remove'])]
    private ?TransportCollectRequest $collect = null;

    #[ORM\ManyToOne(targetEntity: TransportRound::class, inversedBy: 'transportDeliveryRequests')]
    private ?TransportRound $transportRound = null;

    public function __construct() {
        parent::__construct();
        $this->transportDeliveryRequestNatures = new ArrayCollection();
    }

    public function getEmergency(): ?string {
        return $this->emergency;
    }

    public function setEmergency(?string $emergency): self {
        $this->emergency = $emergency;

        return $this;
    }

    /**
     * @return Collection<int, TransportDeliveryRequestNature>
     */
    public function getTransportDeliveryRequestNatures(): Collection {
        return $this->transportDeliveryRequestNatures;
    }

    public function addTransportDeliveryRequestNature(TransportDeliveryRequestNature $transportDeliveryRequestNature): self {
        if (!$this->transportDeliveryRequestNatures->contains($transportDeliveryRequestNature)) {
            $this->transportDeliveryRequestNatures[] = $transportDeliveryRequestNature;
            $transportDeliveryRequestNature->setTransportDeliveryRequest($this);
        }

        return $this;
    }

    public function removeTransportDeliveryRequestNature(TransportDeliveryRequestNature $transportDeliveryRequestNature): self {
        if ($this->transportDeliveryRequestNatures->removeElement($transportDeliveryRequestNature)) {
            // set the owning side to null (unless already changed)
            if ($transportDeliveryRequestNature->getTransportDeliveryRequest() === $this) {
                $transportDeliveryRequestNature->setTransportDeliveryRequest(null);
            }
        }

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

    public function getTransportRound(): ?TransportRound {
        return $this->transportRound;
    }

    public function setTransportRound(?TransportRound $transportRound): self {
        if ($this->transportRound && $this->transportRound !== $transportRound) {
            $this->transportRound->removeTransportDeliveryRequest($this);
        }
        $this->transportRound = $transportRound;
        $transportRound?->addTransportDeliveryRequest($this);

        return $this;
    }

}
