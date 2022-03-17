<?php

namespace App\Entity\Transport;

use App\Entity\Pack;
use App\Entity\Traits\AttachmentTrait;
use App\Repository\Transport\TransportRequestHistoryRepository;
use DateTime;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TransportRequestHistoryRepository::class)]
class TransportRequestHistory
{

    use AttachmentTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'datetime')]
    private ?DateTime $date = null;

    #[ORM\ManyToOne(targetEntity: Pack::class, inversedBy: 'transportRequestHistories')]
    private ?Pack $pack = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $comment = null;

    #[ORM\Column(type: 'string', length: 255)]
    private ?string $category = null;

    #[ORM\ManyToOne(targetEntity: TransportRequest::class, inversedBy: 'transportRequestHistories')]
    private ?TransportRequest $transportRequest = null;

    #[ORM\ManyToOne(targetEntity: TransportOrder::class, inversedBy: 'transportRequestHistories')]
    private ?TransportOrder $transportOrder = null;

    #[ORM\OneToOne(targetEntity: StatusHistory::class, cascade: ['persist', 'remove'])]
    private ?StatusHistory $statusHistory = null;

    public function getId(): ?int
    {
        return $this->id;
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

    public function getPack(): ?Pack
    {
        return $this->pack;
    }

    public function setPack(?Pack $pack): self {
        if($this->pack && $this->pack !== $pack) {
            $this->pack->removeTransportRequestHistory($this);
        }
        $this->pack = $pack;
        $pack?->addTransportRequestHistory($this);

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

    public function getCategory(): ?string
    {
        return $this->category;
    }

    public function setCategory(string $category): self
    {
        $this->category = $category;

        return $this;
    }

    public function getTransportRequest(): ?TransportRequest
    {
        return $this->transportRequest;
    }

    public function setTransportRequest(?TransportRequest $transportRequest): self {
        if($this->transportRequest && $this->transportRequest !== $transportRequest) {
            $this->transportRequest->removeTransportRequestHistory($this);
        }
        $this->transportRequest = $transportRequest;
        $transportRequest?->addTransportRequestHistory($this);

        return $this;
    }

    public function getTransportOrder(): ?TransportOrder
    {
        return $this->transportOrder;
    }

    public function setTransportOrder(?TransportOrder $transportOrder): self {
        if($this->transportOrder && $this->transportOrder !== $transportOrder) {
            $this->transportOrder->removeTransportRequestHistory($this);
        }
        $this->transportOrder = $transportOrder;
        $transportOrder?->addTransportRequestHistory($this);

        return $this;
    }

    public function getStatusHistory(): ?StatusHistory
    {
        return $this->statusHistory;
    }

    public function setStatusHistory(?StatusHistory $statusHistory): self
    {
        $this->statusHistory = $statusHistory;

        return $this;
    }
}
