<?php

namespace App\Entity;

use App\Repository\PurchaseRequestLineRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=PurchaseRequestLineRepository::class)
 */
class PurchaseRequestLine
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\OneToOne(targetEntity=ReferenceArticle::class, cascade={"persist", "remove"})
     * @ORM\JoinColumn(nullable=false)
     */
    private $reference;

    /**
     * @ORM\ManyToMany(targetEntity=PurchaseRequest::class, inversedBy="purchaseRequestLines")
     */
    private $request;

    /**
     * @ORM\Column(type="integer")
     */
    private $requestedQuantity;

    /**
     * @ORM\Column(type="datetime")
     */
    private $orderDate;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $expectedDate;

    /**
     * @ORM\Column(type="integer")
     */
    private $orderNumber;

    /**
     * @ORM\OneToOne(targetEntity=Fournisseur::class, inversedBy="purchaseRequestLine", cascade={"persist", "remove"})
     */
    private $supplier;

    /**
     * @ORM\ManyToOne(targetEntity=Reception::class, inversedBy="purchaseRequestLine")
     */
    private $receptions;

    public function __construct()
    {
        $this->request = new ArrayCollection();
        $this->receptions = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getReference(): ?ReferenceArticle
    {
        return $this->reference;
    }

    public function setReference(ReferenceArticle $reference): self
    {
        if($this->reference && $this->reference->getPurchaseRequestLine() !== $this){
            $oldReference = $this->reference;
            $this->reference= null;
            $oldReference->setPurchaseRequestLine($this);
    }
        $this->reference = $reference;
        if($this->reference && $this->reference->getPurchaseRequestLine() !== $this) {
            $this->reference->setPurchaseRequestLine($this);
        }
        return $this;
    }

    /**
     * @return Collection|PurchaseRequest[]
     */
    public function getRequest(): Collection
    {
        return $this->request;
    }

    public function addRequest(PurchaseRequest $request): self
    {
        if (!$this->request->contains($request)) {
            $this->request[] = $request;
        }

        return $this;
    }

    public function removeRequest(PurchaseRequest $request): self
    {
        if ($this->request->contains($request)) {
            $this->request = $request;
        }

        return $this;
    }

    public function getrequestedQuantity(): ?int
    {
        return $this->requestedQuantity;
    }

    public function setrequestedQuantity(int $requestedQuantity): self
    {
        $this->requestedQuantity = $requestedQuantity;

        return $this;
    }

    public function getOrderDate(): ?\DateTimeInterface
    {
        return $this->orderDate;
    }

    public function setOrderDate(\DateTimeInterface $orderDate): self
    {
        $this->orderDate = $orderDate;

        return $this;
    }

    public function getExpectedDate(): ?\DateTimeInterface
    {
        return $this->expectedDate;
    }

    public function setExpectedDate(?\DateTimeInterface $expectedDate): self
    {
        $this->expectedDate = $expectedDate;

        return $this;
    }

    public function getOrderNumber(): ?int
    {
        return $this->orderNumber;
    }

    public function setOrderNumber(int $orderNumber): self
    {
        $this->orderNumber = $orderNumber;

        return $this;
    }

    public function getSupplier(): ?Fournisseur
    {
        return $this->supplier;
    }

    public function setSupplier(?Fournisseur $supplier): self
    {
        if($this->supplier && $this->supplier->getPurchaseRequestLine() !== $this) {
            $oldSupplier = $this->supplier;
            $this->supplier = null;
            $oldSupplier->setPurchaseRequestLine(null);
        }
        $this->supplier = $supplier;
        if($this->supplier && $this->supplier->getPurchaseRequestLine() !== $this) {
            $this->supplier->setPurchaseRequestLine($this);
        }

        return $this;
    }

    public function getReceptions(): ?Reception {
        return $this->receptions;
    }

    public function setReceptions(?Reception $receptions): self {
        if($this->receptions && $this->receptions !== $receptions) {
            $this->receptions->removePurchaseRequestLine($this);
        }
        $this->receptions = $receptions;
        if($receptions) {
            $receptions->addPurchaseRequestLine($this);
        }

        return $this;
    }
}
