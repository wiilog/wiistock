<?php

namespace App\Entity\PreparationOrder;

use App\Entity\Emplacement;
use App\Entity\ReferenceArticle;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: 'App\Repository\PreparationOrder\PreparationOrderReferenceLineRepository')]
class PreparationOrderReferenceLine {

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'integer')]
    private ?int $quantityToPick = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $pickedQuantity = null;

    #[ORM\ManyToOne(targetEntity: ReferenceArticle::class, inversedBy: 'preparationOrderReferenceLines')]
    #[ORM\JoinColumn(nullable: false)]
    private ?ReferenceArticle $reference = null;

    #[ORM\ManyToOne(targetEntity: Preparation::class, inversedBy: 'referenceLines')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Preparation $preparation = null;

    #[ORM\ManyToOne(targetEntity: Emplacement::class, inversedBy: 'preparationOrderReferenceLines')]
    private ?Emplacement $targetLocationPicking = null;

    public function getId(): ?int {
        return $this->id;
    }

    public function getQuantityToPick(): ?int {
        return $this->quantityToPick;
    }

    public function setQuantityToPick(int $quantityToPick): self {
        $this->quantityToPick = $quantityToPick;

        return $this;
    }

    public function getPickedQuantity(): ?int {
        return $this->pickedQuantity;
    }

    public function setPickedQuantity(?int $pickedQuantity): self {
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

    public function getPreparation(): ?Preparation {
        return $this->preparation;
    }

    public function setPreparation(?Preparation $preparation): self {
        if($this->preparation && $this->preparation !== $preparation) {
            $this->preparation->removeReferenceLine($this);
        }
        $this->preparation = $preparation;
        if($preparation) {
            $preparation->addReferenceLine($this);
        }

        return $this;
    }

    public function getTargetLocationPicking(): ?Emplacement {
        return $this->targetLocationPicking;
    }

    public function setTargetLocationPicking(?Emplacement $targetLocationPicking): self {
        if($this->targetLocationPicking && $this->targetLocationPicking !== $targetLocationPicking) {
            $this->targetLocationPicking->removePreparationOrderReferenceLine($this);
        }

        $this->targetLocationPicking = $targetLocationPicking;

        if($targetLocationPicking) {
            $targetLocationPicking->addPreparationOrderReferenceLine($this);
        }

        return $this;
    }

}
