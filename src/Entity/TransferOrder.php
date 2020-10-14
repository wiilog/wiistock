<?php

namespace App\Entity;

use DateTimeInterface;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity()
 */
class TransferOrder {

    const DRAFT = "Brouillon";
    const TO_TREAT = "À traiter";
    const TREATED = "Traité";

    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

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
        if ($request->getTransferOrder() !== $this) {
            $request->setTransferOrder($this);
        }

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

}
