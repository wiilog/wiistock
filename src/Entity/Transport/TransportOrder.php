<?php

namespace App\Entity\Transport;

use App\Entity\StatusHistory;
use App\Entity\Statut;
use App\Entity\Traits\AttachmentTrait;
use App\Repository\Transport\TransportOrderRepository;
use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use WiiCommon\Helper\Stream;

#[ORM\Entity(repositoryClass: TransportOrderRepository::class)]
class TransportOrder {

    use AttachmentTrait;

    public const NUMBER_PREFIX = 'OTR';

    public const CATEGORY = 'transportOrder';

    public const STATUS_TO_CONTACT = 'Patient à contacter';
    public const STATUS_TO_ASSIGN = 'À affecter';
    public const STATUS_ASSIGNED = 'Affecté';
    public const STATUS_ONGOING = 'En cours';
    public const STATUS_FINISHED = 'Terminé';
    public const STATUS_DEPOSITED = 'Objets déposés';
    public const STATUS_CANCELLED = 'Annulé';
    public const STATUS_NOT_DELIVERED = 'Non livré';
    public const STATUS_NOT_COLLECTED = 'Non collecté';
    public const STATUS_SUBCONTRACTED = 'Sous-traité';
    public const STATUS_AWAITING_VALIDATION = 'En attente de validation';

    public const STATUS_WORKFLOW_COLLECT = [
        self::STATUS_TO_CONTACT,
        self::STATUS_TO_ASSIGN,
        self::STATUS_ASSIGNED,
        self::STATUS_ONGOING,
        self::STATUS_FINISHED,
        self::STATUS_DEPOSITED,
    ];

    public const STATUS_WORKFLOW_DELIVERY = [
        self::STATUS_TO_ASSIGN,
        self::STATUS_ASSIGNED,
        self::STATUS_ONGOING,
        self::STATUS_FINISHED,
    ];

    public const STATUS_WORKFLOW_DELIVERY_COLLECT = [
        self::STATUS_TO_ASSIGN,
        self::STATUS_ASSIGNED,
        self::STATUS_ONGOING,
        self::STATUS_FINISHED,
        self::STATUS_DEPOSITED,
    ];


    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Statut::class)]
    private ?Statut $status = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $subcontractor = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $registrationNumber = null;

    #[ORM\Column(type: 'datetime')]
    private ?DateTime $createdAt = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?DateTime $startedAt = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?DateTime $treatedAt = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $comment = null;

    #[ORM\Column(type: 'boolean')]
    private ?bool $subcontracted = null;

    #[ORM\OneToOne(inversedBy: 'order', targetEntity: TransportRequest::class)]
    private ?TransportRequest $request = null;

    #[ORM\OneToMany(mappedBy: 'order', targetEntity: TransportHistory::class)]
    private Collection $history;

    #[ORM\OneToMany(mappedBy: 'order', targetEntity: TransportDeliveryOrderPack::class)]
    private Collection $packs;

    #[ORM\OneToMany(mappedBy: 'order', targetEntity: TransportRoundLine::class)]
    private Collection $transportRoundLines;

    #[ORM\OneToMany(mappedBy: 'transportOrder', targetEntity: StatusHistory::class)]
    private Collection $statusHistory;

    public function __construct() {
        $this->history = new ArrayCollection();
        $this->packs = new ArrayCollection();
        $this->transportRoundLines = new ArrayCollection();
        $this->statusHistory = new ArrayCollection();
    }

    public function getId(): ?int {
        return $this->id;
    }

    public function getStatus(): ?Statut {
        return $this->status;
    }

    public function setStatus(?Statut $status): self {
        $this->status = $status;

        return $this;
    }

    public function getSubcontractor(): ?string {
        return $this->subcontractor;
    }

    public function setSubcontractor(?string $subcontractor): self {
        $this->subcontractor = $subcontractor;

        return $this;
    }

    public function getRegistrationNumber(): ?string {
        return $this->registrationNumber;
    }

    public function setRegistrationNumber(?string $registrationNumber): self {
        $this->registrationNumber = $registrationNumber;

        return $this;
    }

    public function getCreatedAt(): ?DateTime {
        return $this->createdAt;
    }

    public function setCreatedAt(DateTime $createdAt): self {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getStartedAt(): ?DateTime {
        return $this->startedAt;
    }

    public function setStartedAt(DateTime $startedAt): self {
        $this->startedAt = $startedAt;

        return $this;
    }

    public function getTreatedAt(): ?DateTime {
        return $this->treatedAt;
    }

    public function setTreatedAt(?DateTime $treatedAt): self {
        $this->treatedAt = $treatedAt;

        return $this;
    }

    public function getComment(): ?string {
        return $this->comment;
    }

    public function setComment(?string $comment): self {
        $this->comment = $comment;

        return $this;
    }

    public function isSubcontracted(): ?bool {
        return $this->subcontracted;
    }

    public function setSubcontracted(bool $subcontracted): self {
        $this->subcontracted = $subcontracted;

        return $this;
    }

    public function getRequest(): ?TransportRequest {
        return $this->request;
    }

    public function setRequest(?TransportRequest $request): self {
        if($this->request && $this->request->getOrder() !== $this) {
            $oldRequest = $this->request;
            $this->request = null;
            $oldRequest->setOrder(null);
        }
        $this->request = $request;
        if($this->request && $this->request->getOrder() !== $this) {
            $this->request->setOrder($this);
        }

        return $this;
    }

    /**
     * @return Collection<int, TransportHistory>
     */
    public function getHistory(): Collection {
        return $this->history;
    }

    public function addHistory(TransportHistory $transportHistory): self {
        if (!$this->history->contains($transportHistory)) {
            $this->history[] = $transportHistory;
            $transportHistory->setOrder($this);
        }

        return $this;
    }

    public function removeHistory(TransportHistory $transportHistory): self {
        if ($this->history->removeElement($transportHistory)) {
            // set the owning side to null (unless already changed)
            if ($transportHistory->getOrder() === $this) {
                $transportHistory->setOrder(null);
            }
        }

        return $this;
    }

    public function isRejected(): bool {
        return !$this->getPacks()->isEmpty() && Stream::from($this->getPacks())
            ->filter(fn(TransportDeliveryOrderPack $pack) => !$pack->isRejected())
            ->isEmpty();
    }

    public function hasRejectedPacks(): bool {
        return Stream::from($this->getPacks())
            ->filter(fn(TransportDeliveryOrderPack $pack) => $pack->isRejected())
            ->count();
    }

    public function getPacksForLine(TransportRequestLine $line): Stream {
        $nature = $line->getNature();
        return Stream::from($this->packs)
            ->filter(fn (TransportDeliveryOrderPack $deliveryPack) => $deliveryPack->getPack()?->getNature()?->getId() === $nature->getId());
    }

    /**
     * @return Collection<int, TransportDeliveryOrderPack>
     */
    public function getPacks(): Collection {
        return $this->packs;
    }

    public function addPack(TransportDeliveryOrderPack $pack): self {
        if (!$this->packs->contains($pack)) {
            $this->packs[] = $pack;
            $pack->setOrder($this);
        }

        return $this;
    }

    public function removePack(TransportDeliveryOrderPack $pack): self {
        if ($this->packs->removeElement($pack)) {
            // set the owning side to null (unless already changed)
            if ($pack->getOrder() === $this) {
                $pack->setOrder(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, TransportRoundLine>
     */
    public function getTransportRoundLines(): Collection {
        return $this->transportRoundLines;
    }

    public function addTransportRoundLine(TransportRoundLine $transportRoundLine): self {
        if (!$this->transportRoundLines->contains($transportRoundLine)) {
            $this->transportRoundLines[] = $transportRoundLine;
            $transportRoundLine->setOrder($this);
        }

        return $this;
    }

    public function removeTransportRoundLine(TransportRoundLine $transportRoundLine): self {
        if ($this->transportRoundLines->removeElement($transportRoundLine)) {
            // set the owning side to null (unless already changed)
            if ($transportRoundLine->getOrder() === $this) {
                $transportRoundLine->setOrder(null);
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
            $statusHistory->setTransportOrder($this);
        }

        return $this;
    }

    public function removeStatusHistory(StatusHistory $statusHistory): self {
        if ($this->statusHistory->removeElement($statusHistory)) {
            // set the owning side to null (unless already changed)
            if ($statusHistory->getTransportOrder() === $this) {
                $statusHistory->setTransportOrder(null);
            }
        }

        return $this;
    }

}
