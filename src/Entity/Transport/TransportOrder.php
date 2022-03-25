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

#[ORM\Entity(repositoryClass: TransportOrderRepository::class)]
class TransportOrder {

    use AttachmentTrait;

    public const CATEGORY = 'transportOrder';

    public const STATUS_TO_CONTACT = 'Patient à contacter';
    public const STATUS_TO_ASSIGN = 'À affecter';
    public const STATUS_ASSIGNED = 'Affectée';
    public const STATUS_ONGOING = 'En cours';
    public const STATUS_FINISHED = 'Terminée';
    public const STATUS_DEPOSITED = 'Objets déposés';
    public const STATUS_CANCELLED = 'Annulée';
    public const STATUS_NOT_DELIVERED = 'Non livrée';
    public const STATUS_NOT_COLLECTED = 'Non collectée';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    private ?string $number = null;

    #[ORM\ManyToOne(targetEntity: Statut::class)]
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

    #[ORM\ManyToOne(targetEntity: TransportRequest::class, inversedBy: 'orders')]
    private ?TransportRequest $request = null;

    #[ORM\OneToMany(mappedBy: 'order', targetEntity: TransportRequestHistory::class)]
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

    public function getNumber(): ?string {
        return $this->number;
    }

    public function setNumber(string $number): self {
        $this->number = $number;

        return $this;
    }

    public function getStatus(): ?Statut {
        return $this->status;
    }

    public function setStatus(?Statut $status): self {
        if ($this->status && $this->status !== $status) {
            $this->status->removeTransportOrder($this);
        }
        $this->status = $status;
        $status?->addTransportOrder($this);

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

    public function getSubcontracted(): ?bool {
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
        if ($this->request && $this->request !== $request) {
            $this->request->removeOrder($this);
        }
        $this->request = $request;
        $request?->addOrder($this);

        return $this;
    }

    /**
     * @return Collection<int, TransportRequestHistory>
     */
    public function getHistory(): Collection {
        return $this->history;
    }

    public function addHistory(TransportRequestHistory $transportRequestHistory): self {
        if (!$this->history->contains($transportRequestHistory)) {
            $this->history[] = $transportRequestHistory;
            $transportRequestHistory->setOrder($this);
        }

        return $this;
    }

    public function removeHistory(TransportRequestHistory $transportRequestHistory): self {
        if ($this->history->removeElement($transportRequestHistory)) {
            // set the owning side to null (unless already changed)
            if ($transportRequestHistory->getOrder() === $this) {
                $transportRequestHistory->setOrder(null);
            }
        }

        return $this;
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
