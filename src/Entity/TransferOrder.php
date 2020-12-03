<?php

namespace App\Entity;

use App\Entity\Interfaces\Serializable;
use App\Helper\FormatHelper;
use DateTimeInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use App\Repository\TransferOrderRepository;

/**
 * @ORM\Entity(repositoryClass=TransferOrderRepository::class)
 */
class TransferOrder implements Serializable {

    const NUMBER_PREFIX = 'OT';

    const TO_TREAT = "À traiter";
    const TREATED = "Traité";

    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255, unique=true)
     */
    private $number;

    /**
     * @var Statut|null
     * @ORM\ManyToOne(targetEntity=Statut::class, inversedBy="transferOrders")
     * @ORM\JoinColumn(nullable=false)
     */
    private $status;

    /**
     * @ORM\OneToOne(targetEntity=TransferRequest::class, inversedBy="order")
     * @ORM\JoinColumn(nullable=false)
     */
    private $request;

    /**
     * @ORM\ManyToOne(targetEntity=Utilisateur::class)
     */
    private $operator;

    /**
     * @ORM\Column(type="datetime")
     */
    private $creationDate;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $transferDate;

    /**
     * @ORM\OneToMany(targetEntity=MouvementStock::class, mappedBy="transferOrder")
     */
    private $stockMovements;

    public function __construct()
    {
        $this->stockMovements = new ArrayCollection();
    }

    public function getId(): ?int {
        return $this->id;
    }

    public function getRequest(): ?TransferRequest {
        return $this->request;
    }

    public function setRequest(TransferRequest $request): self {
        $this->request = $request;

        // set the reverse side of the relation if necessary
        if ($request->getOrder() !== $this) {
            $request->setOrder($this);
        }

        return $this;
    }

    public function getCreationDate(): ?DateTimeInterface {
        return $this->creationDate;
    }

    public function setCreationDate(DateTimeInterface $creationDate): self {
        $this->creationDate = $creationDate;
        return $this;
    }

    public function getOperator(): ?Utilisateur {
        return $this->operator;
    }

    public function setOperator(?Utilisateur $operator): self {
        $this->operator = $operator;
        return $this;
    }

    public function getTransferDate(): ?DateTimeInterface {
        return $this->transferDate;
    }

    public function setTransferDate(?DateTimeInterface $transferDate): self {
        $this->transferDate = $transferDate;
        return $this;
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
        $oldStatus = $this->status;
        if ($oldStatus !== $status) {
            $this->status = $status;
            if (isset($this->status)) {
                $this->status->addTransferOrder($this);
            }

            if (isset($oldStatus)) {
                $oldStatus->removeTransferOrder($this);
            }
            $this->status = $status;
        }
        return $this;
    }

    /**
     * @return Collection|MouvementStock[]
     */
    public function getStockMovements(): Collection
    {
        return $this->stockMovements;
    }

    public function addStockMovement(MouvementStock $stockMovement): self
    {
        if (!$this->stockMovements->contains($stockMovement)) {
            $this->stockMovements[] = $stockMovement;
            $stockMovement->setTransferOrder($this);
        }

        return $this;
    }

    public function removeStockMovement(MouvementStock $stockMovement): self
    {
        if ($this->stockMovements->contains($stockMovement)) {
            $this->stockMovements->removeElement($stockMovement);
            // set the owning side to null (unless already changed)
            if ($stockMovement->getTransferOrder() === $this) {
                $stockMovement->setTransferOrder(null);
            }
        }

        return $this;
    }

    public function serialize(): array {
        return [
            'number' => $this->getNumber(),
            'request' => $this->getRequest()->getNumber(),
            'status' => FormatHelper::status($this->getStatus()),
            'requester' => FormatHelper::user($this->getRequest()->getRequester()),
            'operator' => FormatHelper::user($this->getOperator()),
            'origin' => FormatHelper::location($this->getRequest()->getOrigin()),
            'destination' => FormatHelper::location($this->getRequest()->getDestination()),
            'creationDate' => FormatHelper::datetime($this->getCreationDate()),
            'transferDate' => FormatHelper::datetime($this->getTransferDate()),
            'comment' => FormatHelper::html($this->getRequest()->getComment())
        ];
    }

}
