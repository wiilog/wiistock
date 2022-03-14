<?php

namespace App\Entity\Transport;

use App\Entity\Pack;
use App\Entity\Utilisateur;
use App\Repository\TransportDeliveryOrderPackRepository;
use DateTime;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TransportDeliveryOrderPackRepository::class)]
class TransportDeliveryOrderPack
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Pack::class, inversedBy: 'transportDeliveryOrderPacks')]
    private ?Pack $pack = null;

    #[ORM\ManyToOne(targetEntity: TransportOrder::class, inversedBy: 'transportDeliveryOrderPacks')]
    private ?TransportOrder $transportOrder = null;

    #[ORM\Column(type: 'boolean')]
    private ?bool $rejected = null;

    #[ORM\ManyToOne(targetEntity: Utilisateur::class, inversedBy: 'transportDeliveryOrderRejectedPacks')]
    private ?Utilisateur $rejectedBy = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $rejectReason = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?DateTime $returnedAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPack(): ?Pack
    {
        return $this->pack;
    }

    public function setPack(?Pack $pack): self {
        if($this->pack && $this->pack !== $pack) {
            $this->pack->removeTransportDeliveryOrderPack($this);
        }
        $this->pack = $pack;
        $pack?->addTransportDeliveryOrderPack($this);

        return $this;
    }

    public function getTransportOrder(): ?TransportOrder
    {
        return $this->transportOrder;
    }

    public function setTransportOrder(?TransportOrder $transportOrder): self {
        if($this->transportOrder && $this->transportOrder !== $transportOrder) {
            $this->transportOrder->removeTransportDeliveryOrderPack($this);
        }
        $this->transportOrder = $transportOrder;
        $transportOrder?->addTransportDeliveryOrderPack($this);

        return $this;
    }

    public function getRejected(): ?bool
    {
        return $this->rejected;
    }

    public function setRejected(bool $rejected): self
    {
        $this->rejected = $rejected;

        return $this;
    }

    public function getRejectedBy(): ?Utilisateur
    {
        return $this->rejectedBy;
    }

    public function setRejectedBy(?Utilisateur $rejectedBy): self {
        if($this->rejectedBy && $this->rejectedBy !== $rejectedBy) {
            $this->rejectedBy->removeTransportDeliveryOrderRejectedPack($this);
        }
        $this->rejectedBy = $rejectedBy;
        $rejectedBy?->addTransportDeliveryOrderRejectedPack($this);

        return $this;
    }

    public function getRejectReason(): ?string
    {
        return $this->rejectReason;
    }

    public function setRejectReason(?string $rejectReason): self
    {
        $this->rejectReason = $rejectReason;

        return $this;
    }

    public function getReturnedAt(): ?DateTime
    {
        return $this->returnedAt;
    }

    public function setReturnedAt(?DateTime $returnedAt): self
    {
        $this->returnedAt = $returnedAt;

        return $this;
    }
}
