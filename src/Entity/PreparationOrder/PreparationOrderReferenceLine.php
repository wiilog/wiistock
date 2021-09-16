<?php

namespace App\Entity\PreparationOrder;

use App\Entity\ReferenceArticle;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\PreparationOrder\PreparationOrderReferenceLineRepository")
 */
class PreparationOrderReferenceLine
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private ?int $id = null;

    /**
     * @ORM\Column(type="integer")
     */
    private ?int $quantity = null;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private ?int $pickedQuantity = null;

    /**
     * @ORM\ManyToOne(targetEntity=ReferenceArticle::class, inversedBy="preparationOrderReferenceLines")
     * @ORM\JoinColumn(nullable=false)
     */
    private ?ReferenceArticle $reference = null;

    /**
     * @ORM\ManyToOne(targetEntity=Preparation::class, inversedBy="referenceLines")
     * @ORM\JoinColumn(nullable=false, onDelete="CASCADE")
     */
    private ?Preparation $preparation = null;

    public function getId(): ?int {
        return $this->id;
    }

    public function getQuantity(): ?int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): self
    {
        $this->quantity = $quantity;

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

    public function getReference(): ?ReferenceArticle {
        return $this->reference;
    }

    public function setReference(?ReferenceArticle $reference): self {
        if($this->reference && $this->reference !== $reference) {
            $this->reference->removePreparationOrderReferenceLine($this);
        }

        $this->reference = $reference;

        if($reference) {
            $reference->addPreparationOrderReferenceLine($this);
        }

        return $this;
    }

    public function getPreparation(): ?Preparation
    {
        return $this->preparation;
    }

    public function setPreparation(?Preparation $preparation): self
    {
        if($this->preparation && $this->preparation !== $preparation) {
            $this->preparation->removeReferenceLine($this);
        }
        $this->preparation = $preparation;
        if($preparation) {
            $preparation->addReferenceLine($this);
        }

        return $this;
    }
}
