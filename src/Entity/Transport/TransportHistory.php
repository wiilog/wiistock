<?php

namespace App\Entity\Transport;

use App\Entity\Emplacement;
use App\Entity\Pack;
use App\Entity\StatusHistory;
use App\Entity\Traits\AttachmentTrait;
use App\Entity\Utilisateur;
use App\Repository\Transport\TransportHistoryRepository;
use DateTime;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TransportHistoryRepository::class)]
class TransportHistory {

    use AttachmentTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'datetime')]
    private ?DateTime $date = null;

    #[ORM\ManyToOne(targetEntity: TransportRequest::class, inversedBy: 'history')]
    private ?TransportRequest $request = null;

    #[ORM\ManyToOne(targetEntity: TransportOrder::class, inversedBy: 'history')]
    private ?TransportOrder $order = null;

    #[ORM\Column(type: 'string', length: 255)]
    private ?string $type = null;

    #[ORM\ManyToOne(targetEntity: Utilisateur::class)]
    private ?Utilisateur $user = null;

    #[ORM\ManyToOne(targetEntity: TransportRound::class)]
    #[ORM\JoinColumn(onDelete: 'CASCADE')]
    private ?TransportRound $round = null;

    #[ORM\ManyToOne(targetEntity: Utilisateur::class)]
    private ?Utilisateur $deliverer = null;

    #[ORM\ManyToOne(targetEntity: Pack::class, inversedBy: 'transportHistory')]
    private ?Pack $pack = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $reason = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $comment = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $message = null;

    #[ORM\OneToOne(targetEntity: Emplacement::class)]
    private ?Emplacement $location = null;

    #[ORM\ManyToOne(targetEntity: StatusHistory::class, cascade: ['persist'], inversedBy: 'transportHistory')]
    private ?StatusHistory $statusHistory = null;

    public function getId(): ?int {
        return $this->id;
    }

    public function getDate(): ?DateTime {
        return $this->date;
    }

    public function setDate(DateTime $date): self {
        $this->date = $date;

        return $this;
    }

    public function getUser(): ?Utilisateur {
        return $this->user;
    }

    public function setUser(?Utilisateur $user): self {
        $this->user = $user;
        return $this;
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

    public function getComment(): ?string {
        return $this->comment;
    }

    public function setComment(?string $comment): self {
        $this->comment = $comment;

        return $this;
    }

    public function getMessage(): ?string {
        return $this->message;
    }

    public function setMessage(?string $message): self {
        $this->message = $message;

        return $this;
    }

    public function getType(): ?string {
        return $this->type;
    }

    public function setType(string $type): self {
        $this->type = $type;

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
