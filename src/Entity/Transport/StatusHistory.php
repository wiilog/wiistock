<?php

namespace App\Entity\Transport;

use App\Entity\Statut;
use App\Repository\Transport\StatusHistoryRepository;
use DateTime;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: StatusHistoryRepository::class)]
class StatusHistory
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: TransportOrder::class, inversedBy: 'statusHistories')]
    private ?TransportOrder $transportOrder = null;

    #[ORM\ManyToOne(targetEntity: TransportRequest::class, inversedBy: 'statusHistories')]
    private ?TransportRequest $transportRequest = null;

    #[ORM\Column(type: 'datetime')]
    private ?DateTime $date;

    #[ORM\ManyToOne(targetEntity: Statut::class, inversedBy: 'transportStatusHistories')]
    private ?Statut $status = null;

    public function __construct() {
        $this->date = new DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTransportOrder(): ?TransportOrder
    {
        return $this->transportOrder;
    }

    public function setTransportOrder(?TransportOrder $transportOrder): self {
        if($this->transportOrder && $this->transportOrder !== $transportOrder) {
            $this->transportOrder->removeStatusHistory($this);
        }
        $this->transportOrder = $transportOrder;
        $transportOrder?->addStatusHistory($this);

        return $this;
    }

    public function getTransportRequest(): ?TransportRequest
    {
        return $this->transportRequest;
    }

    public function setTransportRequest(?TransportRequest $transportRequest): self {
        if($this->transportRequest && $this->transportRequest !== $transportRequest) {
            $this->transportRequest->removeStatusHistory($this);
        }
        $this->transportRequest = $transportRequest;
        $transportRequest?->addStatusHistory($this);

        return $this;
    }

    public function getDate(): ?DateTime
    {
        return $this->date;
    }

    public function setDate(DateTime $date): self
    {
        $this->date = $date;

        return $this;
    }

    public function getStatus(): ?Statut
    {
        return $this->status;
    }

    public function setStatus(?Statut $status): self {
        if($this->status && $this->status !== $status) {
            $this->status->removeTransportStatusHistory($this);
        }
        $this->status = $status;
        $status?->addTransportStatusHistory($this);

        return $this;
    }
}
