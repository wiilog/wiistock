<?php

namespace App\Entity\Transport;

use App\Entity\Statut;
use App\Entity\Traits\AttachmentTrait;
use App\Repository\Transport\TransportOrderRepository;
use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TransportOrderRepository::class)]
class TransportOrder
{

    use AttachmentTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    private ?string $number = null;

    #[ORM\ManyToOne(targetEntity: Statut::class, inversedBy: 'transportOrders')]
    private ?Statut $status = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $subcontractor = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $registrationNumber = null;

    #[ORM\Column(type: 'datetime')]
    private ?DateTime $startedAt = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?DateTime $treatedAt = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $comment = null;

    #[ORM\Column(type: 'boolean')]
    private ?bool $subcontracted = null;

    #[ORM\ManyToOne(targetEntity: TransportRequest::class, inversedBy: 'transportOrders')]
    private ?TransportRequest $transportRequest = null;

    #[ORM\OneToMany(mappedBy: 'transportOrder', targetEntity: TransportRequestHistory::class)]
    private Collection $transportRequestHistories;

    #[ORM\OneToMany(mappedBy: 'transportOrder', targetEntity: TransportDeliveryOrderPack::class)]
    private Collection $transportDeliveryOrderPacks;

    #[ORM\OneToMany(mappedBy: 'transportOrder', targetEntity: TransportRoundLine::class)]
    private Collection $transportRoundLines;

    #[ORM\OneToMany(mappedBy: 'transportOrder', targetEntity: StatusHistory::class)]
    private Collection $statusHistories;

    public function __construct()
    {
        $this->transportRequestHistories = new ArrayCollection();
        $this->transportDeliveryOrderPacks = new ArrayCollection();
        $this->transportRoundLines = new ArrayCollection();
        $this->statusHistories = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNumber(): ?string
    {
        return $this->number;
    }

    public function setNumber(string $number): self
    {
        $this->number = $number;

        return $this;
    }

    public function getStatus(): ?Statut
    {
        return $this->status;
    }

    public function setStatus(?Statut $status): self {
        if($this->status && $this->status !== $status) {
            $this->status->removeTransportOrder($this);
        }
        $this->status = $status;
        $status?->addTransportOrder($this);

        return $this;
    }

    public function getSubcontractor(): ?string
    {
        return $this->subcontractor;
    }

    public function setSubcontractor(?string $subcontractor): self
    {
        $this->subcontractor = $subcontractor;

        return $this;
    }

    public function getRegistrationNumber(): ?string
    {
        return $this->registrationNumber;
    }

    public function setRegistrationNumber(?string $registrationNumber): self
    {
        $this->registrationNumber = $registrationNumber;

        return $this;
    }

    public function getStartedAt(): ?DateTime
    {
        return $this->startedAt;
    }

    public function setStartedAt(DateTime $startedAt): self
    {
        $this->startedAt = $startedAt;

        return $this;
    }

    public function getTreatedAt(): ?DateTime
    {
        return $this->treatedAt;
    }

    public function setTreatedAt(?DateTime $treatedAt): self
    {
        $this->treatedAt = $treatedAt;

        return $this;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function setComment(?string $comment): self
    {
        $this->comment = $comment;

        return $this;
    }

    public function getSubcontracted(): ?bool
    {
        return $this->subcontracted;
    }

    public function setSubcontracted(bool $subcontracted): self
    {
        $this->subcontracted = $subcontracted;

        return $this;
    }

    public function getTransportRequest(): ?TransportRequest
    {
        return $this->transportRequest;
    }

    public function setTransportRequest(?TransportRequest $transportRequest): self {
        if($this->transportRequest && $this->transportRequest !== $transportRequest) {
            $this->transportRequest->removeTransportOrder($this);
        }
        $this->transportRequest = $transportRequest;
        $transportRequest?->addTransportOrder($this);

        return $this;
    }

    /**
     * @return Collection<int, TransportRequestHistory>
     */
    public function getTransportRequestHistories(): Collection
    {
        return $this->transportRequestHistories;
    }

    public function addTransportRequestHistory(TransportRequestHistory $transportRequestHistory): self
    {
        if (!$this->transportRequestHistories->contains($transportRequestHistory)) {
            $this->transportRequestHistories[] = $transportRequestHistory;
            $transportRequestHistory->setTransportOrder($this);
        }

        return $this;
    }

    public function removeTransportRequestHistory(TransportRequestHistory $transportRequestHistory): self
    {
        if ($this->transportRequestHistories->removeElement($transportRequestHistory)) {
            // set the owning side to null (unless already changed)
            if ($transportRequestHistory->getTransportOrder() === $this) {
                $transportRequestHistory->setTransportOrder(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, TransportDeliveryOrderPack>
     */
    public function getTransportDeliveryOrderPacks(): Collection
    {
        return $this->transportDeliveryOrderPacks;
    }

    public function addTransportDeliveryOrderPack(TransportDeliveryOrderPack $transportDeliveryOrderPack): self
    {
        if (!$this->transportDeliveryOrderPacks->contains($transportDeliveryOrderPack)) {
            $this->transportDeliveryOrderPacks[] = $transportDeliveryOrderPack;
            $transportDeliveryOrderPack->setTransportOrder($this);
        }

        return $this;
    }

    public function removeTransportDeliveryOrderPack(TransportDeliveryOrderPack $transportDeliveryOrderPack): self
    {
        if ($this->transportDeliveryOrderPacks->removeElement($transportDeliveryOrderPack)) {
            // set the owning side to null (unless already changed)
            if ($transportDeliveryOrderPack->getTransportOrder() === $this) {
                $transportDeliveryOrderPack->setTransportOrder(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, TransportRoundLine>
     */
    public function getTransportRoundLines(): Collection
    {
        return $this->transportRoundLines;
    }

    public function addTransportRoundLine(TransportRoundLine $transportRoundLine): self
    {
        if (!$this->transportRoundLines->contains($transportRoundLine)) {
            $this->transportRoundLines[] = $transportRoundLine;
            $transportRoundLine->setTransportOrder($this);
        }

        return $this;
    }

    public function removeTransportRoundLine(TransportRoundLine $transportRoundLine): self
    {
        if ($this->transportRoundLines->removeElement($transportRoundLine)) {
            // set the owning side to null (unless already changed)
            if ($transportRoundLine->getTransportOrder() === $this) {
                $transportRoundLine->setTransportOrder(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, StatusHistory>
     */
    public function getStatusHistories(): Collection
    {
        return $this->statusHistories;
    }

    public function addStatusHistory(StatusHistory $statusHistory): self
    {
        if (!$this->statusHistories->contains($statusHistory)) {
            $this->statusHistories[] = $statusHistory;
            $statusHistory->setTransportOrder($this);
        }

        return $this;
    }

    public function removeStatusHistory(StatusHistory $statusHistory): self
    {
        if ($this->statusHistories->removeElement($statusHistory)) {
            // set the owning side to null (unless already changed)
            if ($statusHistory->getTransportOrder() === $this) {
                $statusHistory->setTransportOrder(null);
            }
        }

        return $this;
    }
}
