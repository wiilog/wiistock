<?php

namespace App\Entity\Transport;

use App\Entity\Nature;
use App\Entity\Tracking\Pack;
use App\Entity\Utilisateur;
use App\Repository\Transport\TransportDeliveryOrderPackRepository;
use DateTime;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TransportDeliveryOrderPackRepository::class)]
class TransportDeliveryOrderPack {

    public const LOADED_STATE = "loaded";
    public const REJECTED_STATE = "rejected";
    public const DELIVERED_STATE = "delivered";
    public const RETURNED_STATE = "returned";

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\OneToOne(inversedBy: 'transportDeliveryOrderPack', targetEntity: Pack::class)]
    private ?Pack $pack = null;

    #[ORM\ManyToOne(targetEntity: TransportOrder::class, inversedBy: 'packs')]
    private ?TransportOrder $order = null;

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $state = null;

    #[ORM\ManyToOne(targetEntity: Utilisateur::class, inversedBy: 'transportDeliveryOrderRejectedPacks')]
    private ?Utilisateur $rejectedBy = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $rejectReason = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?DateTime $returnedAt = null;

    public function getId(): ?int {
        return $this->id;
    }

    public function getPack(): ?Pack {
        return $this->pack;
    }

    public function setPack(?Pack $pack): self {
        if($this->pack && $this->pack->getTransportDeliveryOrderPack() !== $this) {
            $oldPack = $this->pack;
            $this->pack = null;
            $oldPack->setTransportDeliveryOrderPack(null);
        }
        $this->pack = $pack;
        if($this->pack && $this->pack->getTransportDeliveryOrderPack() !== $this) {
            $this->pack->setTransportDeliveryOrderPack($this);
        }

        return $this;
    }

    public function getOrder(): ?TransportOrder {
        return $this->order;
    }

    public function setOrder(?TransportOrder $order): self {
        if ($this->order && $this->order !== $order) {
            $this->order->removePack($this);
        }
        $this->order = $order;
        $order?->addPack($this);

        return $this;
    }

    public function getState(): ?string {
        return $this->state;
    }

    public function setState(string $state): self {
        $this->state = $state;

        return $this;
    }

    public function getRejectedBy(): ?Utilisateur {
        return $this->rejectedBy;
    }

    public function setRejectedBy(?Utilisateur $rejectedBy): self {
        if ($this->rejectedBy && $this->rejectedBy !== $rejectedBy) {
            $this->rejectedBy->removeTransportDeliveryOrderRejectedPack($this);
        }
        $this->rejectedBy = $rejectedBy;
        $rejectedBy?->addTransportDeliveryOrderRejectedPack($this);

        return $this;
    }

    public function getRejectReason(): ?string {
        return $this->rejectReason;
    }

    public function setRejectReason(?string $rejectReason): self {
        $this->rejectReason = $rejectReason;

        return $this;
    }

    public function getReturnedAt(): ?DateTime {
        return $this->returnedAt;
    }

    public function setReturnedAt(?DateTime $returnedAt): self {
        $this->returnedAt = $returnedAt;

        return $this;
    }

    public function getPackTemperature(Nature $nature): ?string
    {
        /** @var TransportDeliveryRequestLine $line */
        $line = $this->order->getRequest()->getLines()
            ->filter(fn(TransportDeliveryRequestLine $line) => $line->getNature() === $nature)
            ->first();
        return $line->getTemperatureRange()?->getValue();
    }
}
