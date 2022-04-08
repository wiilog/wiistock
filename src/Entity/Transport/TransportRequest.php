<?php

namespace App\Entity\Transport;

use App\Entity\StatusHistory;
use App\Entity\Statut;
use App\Entity\Type;
use App\Entity\Utilisateur;
use App\Repository\Transport\TransportRequestRepository;
use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use WiiCommon\Helper\Stream;

#[ORM\Entity(repositoryClass: TransportRequestRepository::class)]
#[ORM\InheritanceType('JOINED')]
#[ORM\DiscriminatorColumn(name: 'discr', type: 'string')]
#[ORM\DiscriminatorMap([
    self::DISCR_DELIVERY => TransportDeliveryRequest::class,
    self::DISCR_COLLECT => TransportCollectRequest::class,
])]
abstract class TransportRequest {

    public const NUMBER_PREFIX = 'DTR';

    public const DISCR_DELIVERY = 'delivery';
    public const DISCR_COLLECT = 'collect';

    public const CATEGORY = 'transportRequest';

    public const STATUS_AWAITING_VALIDATION = 'En attente validation';
    public const STATUS_AWAITING_PLANNING = 'En attente de planification';
    public const STATUS_TO_PREPARE = 'À préparer';
    public const STATUS_TO_DELIVER = 'À livrer';
    public const STATUS_TO_COLLECT = 'À collecter';
    public const STATUS_ONGOING = 'En cours';
    public const STATUS_FINISHED = 'Terminée';
    public const STATUS_DEPOSITED = 'Objets déposés';
    public const STATUS_CANCELLED = 'Annulée';
    public const STATUS_NOT_DELIVERED = 'Non livrée';
    public const STATUS_NOT_COLLECTED = 'Non collectée';
    public const STATUS_SUBCONTRACTED = 'Sous-traitée';

    public const STATUS_COLOR = [
        self::STATUS_AWAITING_VALIDATION => "to-validate",
        self::STATUS_AWAITING_PLANNING => "preparing",
        TransportOrder::STATUS_TO_ASSIGN => "preparing",
        TransportOrder::STATUS_ASSIGNED => "preparing",
        self::STATUS_TO_DELIVER => "preparing",
        self::STATUS_TO_PREPARE => "preparing",
        self::STATUS_TO_COLLECT => "preparing",
        self::STATUS_ONGOING => "ongoing",
        self::STATUS_FINISHED => "finished",
        TransportOrder::STATUS_FINISHED => "finished",
        self::STATUS_CANCELLED => "cancelled",
        TransportOrder::STATUS_CANCELLED => "cancelled",
        self::STATUS_NOT_DELIVERED => "cancelled",
        TransportOrder::STATUS_NOT_DELIVERED => "cancelled",
        self::STATUS_NOT_COLLECTED => "cancelled",
        TransportOrder::STATUS_NOT_COLLECTED => "cancelled",
        self::STATUS_SUBCONTRACTED => "subcontracted",
        TransportOrder::STATUS_SUBCONTRACTED => "subcontracted",
    ];

    public const STATUS_WORKFLOW_DELIVERY_CLASSIC = [
        TransportRequest::STATUS_TO_PREPARE,
        TransportRequest::STATUS_TO_DELIVER,
        TransportRequest::STATUS_ONGOING,
        TransportRequest::STATUS_FINISHED,
    ];

    public const STATUS_WORKFLOW_DELIVERY_COLLECT = [
        TransportRequest::STATUS_TO_PREPARE,
        TransportRequest::STATUS_TO_DELIVER,
        TransportRequest::STATUS_ONGOING,
        TransportRequest::STATUS_FINISHED,
        TransportRequest::STATUS_DEPOSITED,
    ];

    public const STATUS_WORKFLOW_DELIVERY_SUBCONTRACTED = [
        TransportRequest::STATUS_SUBCONTRACTED,
        TransportRequest::STATUS_ONGOING,
        TransportRequest::STATUS_FINISHED,
    ];

    public const STATUS_WORKFLOW_COLLECT = [
        TransportRequest::STATUS_AWAITING_PLANNING,
        TransportRequest::STATUS_TO_COLLECT,
        TransportRequest::STATUS_ONGOING,
        TransportRequest::STATUS_FINISHED,
        TransportRequest::STATUS_DEPOSITED,
    ];

    public const CANCELED_STATUSES = [
        self::STATUS_CANCELLED,
        self::STATUS_NOT_DELIVERED,
        self::STATUS_NOT_COLLECTED
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    private ?string $number = null;

    #[ORM\ManyToOne(targetEntity: Type::class)]
    private ?Type $type = null;

    #[ORM\ManyToOne(targetEntity: Statut::class)]
    private ?Statut $status = null;

    #[ORM\Column(type: 'datetime')]
    private ?DateTime $createdAt = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?DateTime $validationDate = null;

    protected ?DateTime $expectedAt = null;

    #[ORM\ManyToOne(targetEntity: Utilisateur::class, inversedBy: 'transportRequests')]
    private ?Utilisateur $createdBy = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $freeFields = [];

    #[ORM\OneToMany(mappedBy: 'request', targetEntity: TransportOrder::class, cascade: ['persist', 'remove'])]
    private Collection $orders;

    #[ORM\OneToMany(mappedBy: 'request', targetEntity: TransportHistory::class)]
    private Collection $history;

    #[ORM\OneToMany(mappedBy: 'transportRequest', targetEntity: StatusHistory::class)]
    private Collection $statusHistory;

    #[ORM\ManyToOne(targetEntity: TransportRequestContact::class, cascade: ['persist', 'remove'])]
    private ?TransportRequestContact $contact = null;

    #[ORM\OneToMany(mappedBy: 'request', targetEntity: TransportRequestLine::class, cascade: ['remove'])]
    private Collection $lines;

    public function __construct() {
        $this->orders = new ArrayCollection();
        $this->history = new ArrayCollection();
        $this->statusHistory = new ArrayCollection();
        $this->lines = new ArrayCollection();
        $this->contact = $this->contact ?? new TransportRequestContact();
    }

    public abstract function canBeUpdated(): bool;
    public abstract function canBeDeleted(): bool;
    public abstract function canBeCancelled(): bool;

    public function getId(): ?int {
        return $this->id;
    }

    public function getNumber(): ?string {
        return $this->number;
    }

    public function setNumber(string $number): self {
        $this->number = $number;

        return $this;
    }

    public function getType(): ?Type {
        return $this->type;
    }

    public function setType(?Type $type): self {
        $this->type = $type;

        return $this;
    }

    public function getStatus(): ?Statut {
        return $this->status;
    }

    public function setStatus(?Statut $status): self {
        $this->status = $status;
        return $this;
    }

    public function getCreatedAt(): ?DateTime {
        return $this->createdAt;
    }

    public function setCreatedAt(DateTime $createdAt): self {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getValidationDate(): ?DateTime {
        return $this->validationDate;
    }

    public function setValidationDate(?DateTime $validationDate): self {
        $this->validationDate = $validationDate;

        return $this;
    }

    public function getExpectedAt(): ?DateTime {
        return $this->expectedAt;
    }

    public function setExpectedAt(DateTime $expectedAt): self {
        $this->expectedAt = $expectedAt;

        return $this;
    }

    public function getCreatedBy(): ?Utilisateur {
        return $this->createdBy;
    }

    public function setCreatedBy(?Utilisateur $createdBy): self {
        if ($this->createdBy && $this->createdBy !== $createdBy) {
            $this->createdBy->removeTransportRequest($this);
        }
        $this->createdBy = $createdBy;
        $createdBy?->addTransportRequest($this);

        return $this;
    }

    public function getFreeFields(): ?array {
        return $this->freeFields;
    }

    public function setFreeFields(?array $freeFields): self {
        $this->freeFields = $freeFields;

        return $this;
    }

    /**
     * @return Collection<int, TransportOrder>
     */
    public function getOrders(): Collection {
        return $this->orders;
    }

    public function addOrder(TransportOrder $transportOrder): self {
        if (!$this->orders->contains($transportOrder)) {
            $this->orders[] = $transportOrder;
            $transportOrder->setRequest($this);
        }

        return $this;
    }

    public function removeOrder(TransportOrder $transportOrder): self {
        if ($this->orders->removeElement($transportOrder)) {
            // set the owning side to null (unless already changed)
            if ($transportOrder->getRequest() === $this) {
                $transportOrder->setRequest(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, TransportHistory>
     */
    public function getHistory(): Collection {
        return $this->history;
    }

    public function addHistory(TransportHistory $history): self {
        if (!$this->history->contains($history)) {
            $this->history[] = $history;
            $history->setRequest($this);
        }

        return $this;
    }

    public function removeHistory(TransportHistory $history): self {
        if ($this->history->removeElement($history)) {
            // set the owning side to null (unless already changed)
            if ($history->getRequest() === $this) {
                $history->setRequest(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, StatusHistory>
     */
    public function getStatusHistory(): Collection {
        return $this->statusHistory;
    }

    public function addStatusHistory(StatusHistory $statusHistory): self {
        if (!$this->statusHistory->contains($statusHistory)) {
            $this->statusHistory[] = $statusHistory;
            $statusHistory->setTransportRequest($this);
        }

        return $this;
    }

    public function removeStatusHistory(StatusHistory $statusHistory): self {
        if ($this->statusHistory->removeElement($statusHistory)) {
            // set the owning side to null (unless already changed)
            if ($statusHistory->getTransportRequest() === $this) {
                $statusHistory->setTransportRequest(null);
            }
        }

        return $this;
    }

    public function getContact(): ?TransportRequestContact {
        return $this->contact;
    }

    public function setContact(?TransportRequestContact $contact): self {
        $this->contact = $contact;

        return $this;
    }

    public function isInRound(): bool {
        return Stream::from($this->orders)->some(fn(TransportOrder $order) => !$order->getTransportRoundLines()->isEmpty());
    }

    public function isSubcontracted(): bool {
        $lastOrder = $this->getOrders()->last() ?: null;
        return $lastOrder?->isSubcontracted() ?: false;
    }

    /**
     * @return Collection<int, TransportRequestLine>
     */
    public function getLines(): Collection {
        return $this->lines;
    }

    public function addLine(TransportRequestLine $line): self {
        if (!$this->lines->contains($line)) {
            $this->lines[] = $line;
            $line->setRequest($this);
        }

        return $this;
    }

    public function removeLine(TransportRequestLine $line): self {
        if ($this->lines->removeElement($line)) {
            // set the owning side to null (unless already changed)
            if ($line->getRequest() === $this) {
                $line->setRequest(null);
            }
        }

        return $this;
    }

    public function setLines(?array $lines): self {
        foreach($this->getLines()->toArray() as $line) {
            $this->removeLine($line);
        }

        $this->lines = new ArrayCollection();
        foreach($lines as $line) {
            $this->addLine($line);
        }

        return $this;
    }


}
