<?php

namespace App\Entity;

use App\Repository\PurchaseRequestRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=PurchaseRequestRepository::class)
 */
class PurchaseRequest
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\ManyToMany(targetEntity=PurchaseRequestLine::class, mappedBy="request")
     * @ORM\JoinColumn(nullable=false)
     */
    private $purchaseRequestLines;


    /**
     * @ORM\ManyToOne(targetEntity=Utilisateur::class, inversedBy="purchaseRequests")
     * @ORM\JoinColumn(nullable=false)
     */
    private $requester;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private $comment;

    /**
     * @ORM\ManyToOne(targetEntity=Utilisateur::class, inversedBy="purchaseRequests")
     * @ORM\JoinColumn(nullable=false)
     */
    private $buyer;

    /**
     * @ORM\ManyToOne(targetEntity=Statut::class, inversedBy="purchaseRequests")
     * @ORM\JoinColumn(nullable=false)
     */
    private $status;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $creationDate;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $validationDate;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $processingDate;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $considerationDate;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRequester(): ?Utilisateur
    {
        return $this->requester;
    }

    public function setRequester(?Utilisateur $requester): self
    {
        if($this->requester && $this->requester !== $requester){
            $this->requester->removePurchaseRequest($this);
        }
        $this->requester = $requester;
        if($requester) {
            $requester->addPurchaseRequest($this);
        }

        return $this;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function setComment(?string $comment): self
    {
        $this->comment = $comment;

        return $this;
    }

    public function getBuyer(): ?Utilisateur
    {
        return $this->buyer;
    }

    public function setBuyer(?Utilisateur $buyer): self
    {
        if($this->buyer && $this->buyer !== $buyer){
            $this->buyer->removePurchaseRequest($this);
        }
        $this->buyer = $buyer;
        if($buyer) {
            $buyer->addPurchaseRequest($this);
        }

        return $this;
    }

    public function getStatus(): ?Statut
    {
        return $this->status;
    }

    public function setStatus(?Statut $status): self
    {
        if($this->status && $this->status !== $status){
            $this->status->removePurchaseRequest($this);
        }
        $this->status = $status;
        if($status) {
            $status->addPurchaseRequest($this);
        }

        return $this;
    }

    public function getCreationDate(): ?\DateTimeInterface
    {
        return $this->creationDate;
    }

    public function setCreationDate(?\DateTimeInterface $creationDate): self
    {
        $this->creationDate = $creationDate;

        return $this;
    }

    public function getValidationDate(): ?\DateTimeInterface
    {
        return $this->validationDate;
    }

    public function setValidationDate(?\DateTimeInterface $validationDate): self
    {
        $this->validationDate = $validationDate;

        return $this;
    }

    public function getProcessingDate(): ?\DateTimeInterface
    {
        return $this->processingDate;
    }

    public function setProcessingDate(?\DateTimeInterface $processingDate): self
    {
        $this->processingDate = $processingDate;

        return $this;
    }

    public function getConsiderationDate(): ?\DateTimeInterface
    {
        return $this->considerationDate;
    }

    public function setConsiderationDate(?\DateTimeInterface $considerationDate): self
    {
        $this->considerationDate = $considerationDate;

        return $this;
    }

    /**
     * @return Collection|PurchaseRequestLine[]
     */
    public function getPurchaseRequestLine(): Collection {
        return $this->purchaseRequestLines;
    }

    public function addPurchaseRequestLine(PurchaseRequestLine $purchaseRequestLines): self {
        if (!$this->purchaseRequestLines->contains($purchaseRequestLines)) {
            $this->purchaseRequestLines[] = $purchaseRequestLines;
            $purchaseRequestLines->addPurchaseRequest($this);
        }

        return $this;
    }

    public function removePurchaseRequestLine(PurchaseRequestLine $purchaseRequestLines): self {
        if ($this->purchaseRequestLines->removeElement($purchaseRequestLines)) {
            $purchaseRequestLines->removePurchaseRequest($this);
        }

        return $this;
    }

    public function setPurchaseRequestLine(?array $purchaseRequestLines): self {
        foreach($this->getPurchaseRequestLine()->toArray() as $purchaseRequestLine) {
            $this->removePurchaseRequestLine($purchaseRequestLine);
        }

        $this->purchaseRequestLines = new ArrayCollection();
        foreach($purchaseRequestLines as $purchaseRequestLine) {
            $this->addPurchaseRequestLine()($purchaseRequestLine);
        }

        return $this;
    }
}
