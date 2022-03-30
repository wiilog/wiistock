<?php

namespace App\Entity\Transport;

use App\Entity\Statut;
use App\Entity\Type;
use App\Entity\Utilisateur;
use App\Repository\Transport\TransportRequestRepository;
use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use JetBrains\PhpStorm\Pure;

#[ORM\Entity(repositoryClass: TransportRequestRepository::class)]
#[ORM\InheritanceType('JOINED')]
#[ORM\DiscriminatorColumn(name: 'discr', type: 'string')]
#[ORM\DiscriminatorMap([
      self::DISCR_DELIVERY => TransportDeliveryRequest::class,
      self::DISCR_COLLECT => TransportCollectRequest::class
 ])]
abstract class TransportRequest {

    public const NUMBER_PREFIX = 'DTR';

    public const DISCR_DELIVERY = 'delivery';
    public const DISCR_COLLECT = 'collect';

    public const CATEGORY = 'transportRequest';

    public const STATUS_AWAITING_VALIDATION = 'En attente validation';
    public const STATUS_AWAITING_PLANNING = 'En attente de plannification';
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

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    private ?string $number = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?DateTime $expectedAt = null;

    #[ORM\ManyToOne(targetEntity: Type::class, inversedBy: 'transportRequests')]
    private ?Type $type = null;

    #[ORM\ManyToOne(targetEntity: Statut::class, inversedBy: 'transportRequests')]
    private ?Statut $status = null;

    #[ORM\Column(type: 'datetime')]
    private ?DateTime $createdAt = null;

    #[ORM\ManyToOne(targetEntity: Utilisateur::class, inversedBy: 'transportRequests')]
    private ?Utilisateur $createdBy = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $freeFields = [];

    #[ORM\OneToMany(mappedBy: 'transportRequest', targetEntity: TransportOrder::class)]
    private Collection $transportOrders;

    #[ORM\OneToMany(mappedBy: 'transportRequest', targetEntity: TransportHistory::class)]
    private Collection $transportRequestHistories;

    #[ORM\OneToMany(mappedBy: 'transportRequest', targetEntity: StatusHistory::class)]
    private Collection $statusHistories;

    #[ORM\ManyToOne(targetEntity: TransportRequestContact::class, cascade: ['persist', 'remove'])]
    private ?TransportRequestContact $contact = null;

    #[Pure] public function __construct() {
        $this->transportOrders = new ArrayCollection();
        $this->transportRequestHistories = new ArrayCollection();
        $this->statusHistories = new ArrayCollection();
        $this->contact = $this->contact ?? new TransportRequestContact();
    }

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
        if ($this->type && $this->type !== $type) {
            $this->type->removeTransportRequest($this);
        }
        $this->type = $type;
        $type?->addTransportRequest($this);

        return $this;
    }

    public function getStatus(): ?Statut {
        return $this->status;
    }

    public function setStatus(?Statut $status): self {
        if ($this->status && $this->status !== $status) {
            $this->status->removeTransportRequest($this);
        }
        $this->status = $status;
        $status?->addTransportRequest($this);

        return $this;
    }

    public function getCreatedAt(): ?DateTime {
        return $this->createdAt;
    }

    public function setCreatedAt(DateTime $createdAt): self {
        $this->createdAt = $createdAt;

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
    public function getTransportOrders(): Collection {
        return $this->transportOrders;
    }

    public function addTransportOrder(TransportOrder $transportOrder): self {
        if (!$this->transportOrders->contains($transportOrder)) {
            $this->transportOrders[] = $transportOrder;
            $transportOrder->setTransportRequest($this);
        }

        return $this;
    }

    public function removeTransportOrder(TransportOrder $transportOrder): self {
        if ($this->transportOrders->removeElement($transportOrder)) {
            // set the owning side to null (unless already changed)
            if ($transportOrder->getTransportRequest() === $this) {
                $transportOrder->setTransportRequest(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, TransportHistory>
     */
    public function getTransportRequestHistories(): Collection {
        return $this->transportRequestHistories;
    }

    public function addTransportHistory(TransportHistory $transportHistory): self {
        if (!$this->transportRequestHistories->contains($transportHistory)) {
            $this->transportRequestHistories[] = $transportHistory;
            $transportHistory->setTransportRequest($this);
        }

        return $this;
    }

    public function removeTransportHistory(TransportHistory $transportHistory): self {
        if ($this->transportRequestHistories->removeElement($transportHistory)) {
            // set the owning side to null (unless already changed)
            if ($transportHistory->getTransportRequest() === $this) {
                $transportHistory->setTransportRequest(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, StatusHistory>
     */
    public function getHistory(): Collection {
        return $this->statusHistories;
    }

    public function addStatusHistory(StatusHistory $statusHistory): self {
        if (!$this->statusHistories->contains($statusHistory)) {
            $this->statusHistories[] = $statusHistory;
            $statusHistory->setTransportRequest($this);
        }

        return $this;
    }

    public function removeStatusHistory(StatusHistory $statusHistory): self {
        if ($this->statusHistories->removeElement($statusHistory)) {
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

    public function getExpectedAt(): ?DateTime {
        return $this->expectedAt;
    }

    public function setExpectedAt(?DateTime $expectedAt): self {
        $this->expectedAt = $expectedAt;
        return $this;
    }
}
