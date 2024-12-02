<?php

namespace App\Entity\OperationHistory;

use App\Entity\Emplacement;
use App\Entity\StatusHistory;
use App\Entity\Tracking\Pack;
use App\Entity\Traits\AttachmentTrait;
use App\Entity\Transport\TransportOrder;
use App\Entity\Transport\TransportRequest;
use App\Entity\Transport\TransportRound;
use App\Entity\Utilisateur;
use App\Repository\Transport\TransportHistoryRecordRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TransportHistoryRecordRepository::class)]
class TransportHistoryRecord extends OperationHistory {

    use AttachmentTrait;

    #[ORM\ManyToOne(targetEntity: TransportRequest::class, inversedBy: 'history')]
    #[ORM\JoinColumn(nullable: true)]
    private ?TransportRequest $request = null;

    #[ORM\ManyToOne(targetEntity: TransportOrder::class, inversedBy: 'history')]
    #[ORM\JoinColumn(nullable: true)]
    private ?TransportOrder $order = null;

    #[ORM\ManyToOne(targetEntity: TransportRound::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?TransportRound $round = null;

    #[ORM\ManyToOne(targetEntity: Utilisateur::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Utilisateur $deliverer = null;

    #[ORM\ManyToOne(targetEntity: Pack::class, inversedBy: 'transportHistory')]
    #[ORM\JoinColumn(nullable: true)]
    private ?Pack $pack = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $reason = null;

    #[ORM\ManyToOne(targetEntity: Emplacement::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Emplacement $location = null;

    #[ORM\ManyToOne(targetEntity: StatusHistory::class, cascade: ['persist'], inversedBy: 'transportHistory')]
    #[ORM\JoinColumn(nullable: true)]
    private ?StatusHistory $statusHistory = null;

    public function __construct() {
        $this->attachments = new ArrayCollection();
    }

    public function getRound(): ?TransportRound {
        return $this->round;
    }

    public function setRound(?TransportRound $round): self {
        $this->round = $round;
        return $this;
    }

    public function getDeliverer(): ?Utilisateur {
        return $this->deliverer;
    }

    public function setDeliverer(?Utilisateur $deliverer): self {
        $this->deliverer = $deliverer;
        return $this;
    }

    public function getLocation(): ?Emplacement {
        return $this->location;
    }

    public function setLocation(?Emplacement $location): self {
        $this->location = $location;
        return $this;
    }

    public function getPack(): ?Pack {
        return $this->pack;
    }

    public function setPack(?Pack $pack): self {
        if ($this->pack && $this->pack !== $pack) {
            $this->pack->removeTransportHistory($this);
        }
        $this->pack = $pack;
        $pack?->addTransportHistory($this);

        return $this;
    }

    public function getReason(): ?string {
        return $this->reason;
    }

    public function setReason(?string $reason): self {
        $this->reason = $reason;
        return $this;
    }

    public function getRequest(): ?TransportRequest {
        return $this->request;
    }

    public function setRequest(?TransportRequest $request): self {
        if ($this->request && $this->request !== $request) {
            $this->request->removeHistory($this);
        }
        $this->request = $request;
        $request?->addHistory($this);

        return $this;
    }

    public function getOrder(): ?TransportOrder {
        return $this->order;
    }

    public function setOrder(?TransportOrder $order): self {
        if ($this->order && $this->order !== $order) {
            $this->order->removeHistory($this);
        }
        $this->order = $order;
        $order?->addHistory($this);

        return $this;
    }

    public function getStatusHistory(): ?StatusHistory {
        return $this->statusHistory;
    }

    public function setStatusHistory(?StatusHistory $statusHistory): self {
        if($this->statusHistory && $this->statusHistory !== $statusHistory) {
            $this->statusHistory->removeTransportHistory($this);
        }
        $this->statusHistory = $statusHistory;
        $statusHistory?->addTransportHistory($this);

        return $this;
    }

}
