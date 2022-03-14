<?php

namespace App\Entity\Transport;

use App\Entity\Statut;
use App\Entity\Type;
use App\Entity\Utilisateur;
use App\Repository\TransportRequestRepository;
use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TransportRequestRepository::class)]
#[ORM\InheritanceType('JOINED')]
#[ORM\DiscriminatorColumn(name: 'discr', type: 'string')]
abstract class TransportRequest
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    private ?string $number = null;

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

    #[ORM\OneToMany(mappedBy: 'transportRequest', targetEntity: TransportRequestHistory::class)]
    private Collection $transportRequestHistories;

    #[ORM\OneToMany(mappedBy: 'transportRequest', targetEntity: StatusHistory::class)]
    private Collection $statusHistories;

    #[ORM\OneToOne(targetEntity: TransportRequestContact::class, cascade: ['persist', 'remove'])]
    private ?TransportRequestContact $transportRequestContact = null;

    public function __construct()
    {
        $this->transportOrders = new ArrayCollection();
        $this->transportRequestHistories = new ArrayCollection();
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

    public function getType(): ?Type
    {
        return $this->type;
    }

    public function setType(?Type $type): self {
        if($this->type && $this->type !== $type) {
            $this->type->removeTransportRequest($this);
        }
        $this->type = $type;
        $type?->addTransportRequest($this);

        return $this;
    }

    public function getStatus(): ?Statut
    {
        return $this->status;
    }

    public function setStatus(?Statut $status): self {
        if($this->status && $this->status !== $status) {
            $this->status->removeTransportRequest($this);
        }
        $this->status = $status;
        $status?->addTransportRequest($this);

        return $this;
    }

    public function getCreatedAt(): ?DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(DateTime $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getCreatedBy(): ?Utilisateur
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?Utilisateur $createdBy): self {
        if($this->createdBy && $this->createdBy !== $createdBy) {
            $this->createdBy->removeTransportRequest($this);
        }
        $this->createdBy = $createdBy;
        $createdBy?->addTransportRequest($this);

        return $this;
    }

    public function getFreeFields(): ?array
    {
        return $this->freeFields;
    }

    public function setFreeFields(?array $freeFields): self
    {
        $this->freeFields = $freeFields;

        return $this;
    }

    /**
     * @return Collection<int, TransportOrder>
     */
    public function getTransportOrders(): Collection
    {
        return $this->transportOrders;
    }

    public function addTransportOrder(TransportOrder $transportOrder): self
    {
        if (!$this->transportOrders->contains($transportOrder)) {
            $this->transportOrders[] = $transportOrder;
            $transportOrder->setTransportRequest($this);
        }

        return $this;
    }

    public function removeTransportOrder(TransportOrder $transportOrder): self
    {
        if ($this->transportOrders->removeElement($transportOrder)) {
            // set the owning side to null (unless already changed)
            if ($transportOrder->getTransportRequest() === $this) {
                $transportOrder->setTransportRequest(null);
            }
        }

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
            $transportRequestHistory->setTransportRequest($this);
        }

        return $this;
    }

    public function removeTransportRequestHistory(TransportRequestHistory $transportRequestHistory): self
    {
        if ($this->transportRequestHistories->removeElement($transportRequestHistory)) {
            // set the owning side to null (unless already changed)
            if ($transportRequestHistory->getTransportRequest() === $this) {
                $transportRequestHistory->setTransportRequest(null);
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
            $statusHistory->setTransportRequest($this);
        }

        return $this;
    }

    public function removeStatusHistory(StatusHistory $statusHistory): self
    {
        if ($this->statusHistories->removeElement($statusHistory)) {
            // set the owning side to null (unless already changed)
            if ($statusHistory->getTransportRequest() === $this) {
                $statusHistory->setTransportRequest(null);
            }
        }

        return $this;
    }

    public function getTransportRequestContact(): ?TransportRequestContact
    {
        return $this->transportRequestContact;
    }

    public function setTransportRequestContact(?TransportRequestContact $transportRequestContact): self
    {
        $this->transportRequestContact = $transportRequestContact;

        return $this;
    }
}
