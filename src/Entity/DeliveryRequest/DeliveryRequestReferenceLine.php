<?php

namespace App\Entity\DeliveryRequest;

use App\Entity\ReferenceArticle;
use Doctrine\ORM\Mapping as ORM;
use App\Repository\DeliveryRequest\DeliveryRequestReferenceLineRepository;

/**
 * @ORM\Entity(repositoryClass=DeliveryRequestReferenceLineRepository::class)
 */
class DeliveryRequestReferenceLine
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private ?int $id = null;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private ?int $quantity = null;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private ?int $pickedQuantity = null;

    /**
     * @ORM\ManyToOne(targetEntity=ReferenceArticle::class, inversedBy="deliveryRequestLines")
     */
    private ?ReferenceArticle $reference = null;

    /**
     * @ORM\ManyToOne(targetEntity=Demande::class, inversedBy="referenceLines")
     * @ORM\JoinColumn(onDelete="CASCADE")
     */
    private ?Demande $request = null;

    public function getId(): ?int {
        return $this->id;
    }

    public function getQuantity(): ?int
    {
        return $this->quantity;
    }

    public function setQuantity(?int $quantity): self
    {
        $this->quantity = $quantity;

        return $this;
    }

    public function getReference(): ?ReferenceArticle
    {
        return $this->reference;
    }

    public function setReference(?ReferenceArticle $reference): self
    {
        if($this->reference && $this->reference !== $reference) {
            $this->reference->removeDeliveryRequestReferenceLine($this);
        }

        $this->reference = $reference;

        if($reference) {
            $reference->addDeliveryRequestReferenceLine($this);
        }

        return $this;
    }

    public function getRequest(): ?Demande {
        return $this->request;
    }

    public function setRequest(?Demande $request): self
    {
        if($this->request && $this->request !== $request) {
            $this->request->removeReferenceLine($this);
        }

        $this->request = $request;

        if($request) {
            $request->addReferenceLine($this);
        }

        return $this;
    }

    public function getPickedQuantity(): ?int
    {
        return $this->pickedQuantity;
    }

    public function setPickedQuantity(?int $pickedQuantity): self
    {
        $this->pickedQuantity = $pickedQuantity;

        return $this;
    }

}
