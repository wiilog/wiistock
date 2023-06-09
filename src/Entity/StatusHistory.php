<?php

namespace App\Entity;

use App\Entity\ShippingRequest\ShippingRequest;
use App\Entity\Transport\TransportHistory;
use App\Entity\Transport\TransportOrder;
use App\Entity\Transport\TransportRequest;
use App\Entity\Transport\TransportRound;
use App\Repository\Transport\StatusHistoryRepository;
use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: StatusHistoryRepository::class)]
class StatusHistory {

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    // TODO WIIS-9630 rajouter des on cascade delete pour supprimer les statusHistory
    #[ORM\ManyToOne(targetEntity: TransportOrder::class, inversedBy: 'statusHistory')]
    private ?TransportOrder $transportOrder = null;

    #[ORM\ManyToOne(targetEntity: TransportRequest::class, inversedBy: 'statusHistory')]
    private ?TransportRequest $transportRequest = null;

    #[ORM\ManyToOne(targetEntity: TransportRound::class, inversedBy: 'statusHistory')]
    private ?TransportRound $transportRound = null;

    #[ORM\ManyToOne(targetEntity: ShippingRequest::class, inversedBy: 'statusHistory')]
    private ?ShippingRequest $shippingRequest = null;

    #[ORM\ManyToOne(targetEntity: Handling::class, inversedBy: 'statusHistory')]
    #[ORM\JoinColumn(onDelete: 'CASCADE')]
    private ?Handling $Handling = null;

    #[ORM\ManyToOne(targetEntity: Dispatch::class, inversedBy: 'statusHistory')]
    #[ORM\JoinColumn(onDelete: 'CASCADE')]
    private ?Dispatch $dispatch = null;

    #[ORM\Column(type: 'datetime')]
    private ?DateTime $date;

    #[ORM\ManyToOne(targetEntity: Statut::class)]
    private ?Statut $status = null;

    #[ORM\OneToMany(mappedBy: 'statusHistory', targetEntity: TransportHistory::class)]
    private Collection $transportHistory;

    #[ORM\ManyToOne(targetEntity: Utilisateur::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Utilisateur $initiatedBy = null;

    #[ORM\ManyToOne(targetEntity: Utilisateur::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Utilisateur $validatedBy = null;

    public function __construct() {
        $this->date = new DateTime();
        $this->transportHistory = new ArrayCollection();
    }

    public function getId(): ?int {
        return $this->id;
    }

    public function getTransportOrder(): ?TransportOrder {
        return $this->transportOrder;
    }

    public function setTransportOrder(?TransportOrder $transportOrder): self {
        if ($this->transportOrder && $this->transportOrder !== $transportOrder) {
            $this->transportOrder->removeStatusHistory($this);
        }
        $this->transportOrder = $transportOrder;
        $transportOrder?->addStatusHistory($this);

        return $this;
    }

    public function getTransportRequest(): ?TransportRequest {
        return $this->transportRequest;
    }

    public function setTransportRequest(?TransportRequest $transportRequest): self {
        if ($this->transportRequest && $this->transportRequest !== $transportRequest) {
            $this->transportRequest->removeStatusHistory($this);
        }
        $this->transportRequest = $transportRequest;
        $transportRequest?->addStatusHistory($this);

        return $this;
    }

    public function getTransportRound(): ?TransportRound {
        return $this->transportRound;
    }

    public function setTransportRound(?TransportRound $transportRound): self {
        if ($this->transportRound && $this->transportRound !== $transportRound) {
            $this->transportRound->removeStatusHistory($this);
        }
        $this->transportRound = $transportRound;
        $transportRound?->addStatusHistory($this);

        return $this;
    }

    public function getHandling(): ?Handling{
        return $this->Handling;
    }

    public function setHandling(?Handling $Handling): self{
        $this->Handling = $Handling;

        return $this;
    }

    public function getDate(): ?DateTime {
        return $this->date;
    }

    public function setDate(DateTime $date): self {
        $this->date = $date;

        return $this;
    }

    public function getStatus(): ?Statut {
        return $this->status;
    }

    public function setStatus(?Statut $status): self {
        $this->status = $status;

        return $this;
    }

    public function getTransportHistory(): Collection {
        return $this->transportHistory;
    }

    public function addTransportHistory(TransportHistory $transportHistory): self {
        if (!$this->transportHistory->contains($transportHistory)) {
            $this->transportHistory[] = $transportHistory;
            $transportHistory->setStatusHistory($this);
        }

        return $this;
    }

    public function removeTransportHistory(TransportHistory $transportHistory): self {
        if ($this->transportHistory->removeElement($transportHistory)) {
            if ($transportHistory->getStatusHistory() === $this) {
                $transportHistory->setStatusHistory(null);
            }
        }

        return $this;
    }

    public function setTransportHistory(?array $transportHistory): self {
        foreach($this->getTransportHistory()->toArray() as $history) {
            $this->removeTransportHistory($history);
        }

        $this->transportHistory = new ArrayCollection();
        foreach($transportHistory as $history) {
            $this->addTransportHistory($history);
        }

        return $this;
    }

    public function getDispatch(): ?Dispatch {
        return $this->dispatch;
    }

    public function setDispatch(?Dispatch $dispatch): self {
        if($this->dispatch && $this->dispatch !== $dispatch) {
            $this->dispatch->removeStatusHistory($this);
        }
        $this->dispatch = $dispatch;
        $dispatch?->addStatusHistory($this);

        return $this;
    }

    public function getShippingRequest(): ?ShippingRequest {
        return $this->shippingRequest;
    }

    public function setShippingRequest(?ShippingRequest $shippingRequest): self {
        if($this->shippingRequest && $this->shippingRequest !== $shippingRequest) {
            $this->shippingRequest->removeStatusHistory($this);
        }
        $this->shippingRequest = $shippingRequest;
        $shippingRequest?->addStatusHistory($this);

        return $this;
    }

    public function getValidatedBy(): ?utilisateur
    {
        return $this->validatedBy;
    }

    public function setValidatedBy(?utilisateur $validatedBy): self
    {
        $this->validatedBy = $validatedBy;

        return $this;
    }

    public function getInitiatedBy(): ?utilisateur
    {
        return $this->initiatedBy;
    }

    public function setInitiatedBy(?utilisateur $initiatedBy): self
    {
        $this->initiatedBy = $initiatedBy;

        return $this;
    }

}
