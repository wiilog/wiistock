<?php

namespace App\Entity;

use DateTimeInterface;
use Doctrine\ORM\Mapping as ORM;
use App\Repository\TransferOrderRepository;

/**
 * @ORM\Entity(repositoryClass=TransferOrderRepository::class)
 */
class TransferOrder {

    const TO_TREAT = "À traiter";
    const TREATED = "Traité";

    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $number;

    /**
     * @ORM\ManyToOne(targetEntity=Statut::class)
     * @ORM\JoinColumn(nullable=false)
     */
    private $status;

    /**
     * @ORM\OneToOne(targetEntity=TransferRequest::class, inversedBy="order", cascade={"persist", "remove"})
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
        $this->status = $status;
        return $this;
    }

}
