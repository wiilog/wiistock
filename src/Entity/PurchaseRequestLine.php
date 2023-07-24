<?php

namespace App\Entity;

use App\Repository\PurchaseRequestLineRepository;
use DateTimeInterface;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PurchaseRequestLineRepository::class)]
class PurchaseRequestLine {

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'integer')]
    private ?int $requestedQuantity = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $orderedQuantity = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?DateTimeInterface $orderDate = null;

    #[ORM\Column(type: 'date', nullable: true)]
    private ?DateTimeInterface $expectedDate = null;

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $orderNumber = null;

    #[ORM\ManyToOne(targetEntity: ReferenceArticle::class, inversedBy: 'purchaseRequestLines')]
    #[ORM\JoinColumn(nullable: true)]
    private ?ReferenceArticle $reference = null;

    #[ORM\ManyToOne(targetEntity: Emplacement::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Emplacement $location = null;

    #[ORM\ManyToOne(targetEntity: PurchaseRequest::class, inversedBy: 'purchaseRequestLines')]
    #[ORM\JoinColumn(nullable: false)]
    private ?PurchaseRequest $purchaseRequest = null;

    #[ORM\ManyToOne(targetEntity: Fournisseur::class, inversedBy: 'purchaseRequestLines')]
    #[ORM\JoinColumn(nullable: true)]
    private ?Fournisseur $supplier = null;

    #[ORM\ManyToOne(targetEntity: Reception::class, inversedBy: 'purchaseRequestLines')]
    private ?Reception $reception = null;

    public function getId(): ?int {
        return $this->id;
    }

    public function getReference(): ?ReferenceArticle {
        return $this->reference;
    }

    public function setReference(?ReferenceArticle $reference): self {
        if($this->reference && $this->reference !== $reference) {
            $this->reference->removePurchaseRequestLine($this);
        }
        $this->reference = $reference;
        if($reference) {
            $reference->addPurchaseRequestLine($this);
        }

        return $this;
    }

    public function getLocation(): ?Emplacement {
        return $this->location;
    }

    public function setLocation(?Emplacement $location): self {
        $this->location = $location;

        return $this;
    }

    public function getPurchaseRequest(): ?PurchaseRequest {
        return $this->purchaseRequest;
    }

    public function setPurchaseRequest(?PurchaseRequest $purchaseRequest): self {
        if($this->purchaseRequest && $this->purchaseRequest !== $purchaseRequest) {
            $this->purchaseRequest->removePurchaseRequestLine($this);
        }
        $this->purchaseRequest = $purchaseRequest;
        if($purchaseRequest) {
            $purchaseRequest->addPurchaseRequestLine($this);
        }

        return $this;
    }

    public function getRequestedQuantity(): ?int {
        return $this->requestedQuantity;
    }

    public function setRequestedQuantity(int $requestedQuantity): self {
        $this->requestedQuantity = $requestedQuantity;

        return $this;
    }

    public function getOrderedQuantity(): ?int {
        return $this->orderedQuantity;
    }

    public function setOrderedQuantity(int $orderedQuantity): self {
        $this->orderedQuantity = $orderedQuantity;

        return $this;
    }

    public function getOrderDate(): ?DateTimeInterface {
        return $this->orderDate;
    }

    public function setOrderDate(?DateTimeInterface $orderDate): self {
        $this->orderDate = $orderDate;

        return $this;
    }

    public function getExpectedDate(): ?DateTimeInterface {
        return $this->expectedDate;
    }

    public function setExpectedDate(?DateTimeInterface $expectedDate): self {
        $this->expectedDate = $expectedDate;

        return $this;
    }

    public function getOrderNumber(): ?string {
        return $this->orderNumber;
    }

    public function setOrderNumber(?string $orderNumber): self {
        $this->orderNumber = $orderNumber;

        return $this;
    }

    public function getSupplier(): ?Fournisseur {
        return $this->supplier;
    }

    public function setSupplier(?Fournisseur $supplier): self {
        if($this->supplier && $this->supplier !== $supplier) {
            $this->supplier->removePurchaseRequestLine($this);
        }

        $this->supplier = $supplier;

        if($supplier) {
            $supplier->addPurchaseRequestLine($this);
        }
        return $this;
    }

    public function getReception(): ?Reception {
        return $this->reception;
    }

    public function setReception(?Reception $reception): self {
        if($this->reception && $this->reception !== $reception) {
            $this->reception->removePurchaseRequestLine($this);
        }

        $this->reception = $reception;

        if($reception) {
            $reception->addPurchaseRequestLine($this);
        }
        return $this;
    }

}
