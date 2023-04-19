<?php

namespace App\Entity\DeliveryRequest;

use App\Entity\Emplacement;
use App\Entity\ReferenceArticle;
use App\Repository\DeliveryRequest\DeliveryRequestReferenceLineRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DeliveryRequestReferenceLineRepository::class)]
class DeliveryRequestReferenceLine {

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $quantityToPick = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $pickedQuantity = null;

    #[ORM\ManyToOne(targetEntity: ReferenceArticle::class, inversedBy: 'deliveryRequestLines')]
    private ?ReferenceArticle $reference = null;

    #[ORM\ManyToOne(targetEntity: Demande::class, inversedBy: 'referenceLines')]
    #[ORM\JoinColumn(onDelete: 'CASCADE')]
    private ?Demande $request = null;

    #[ORM\ManyToOne(targetEntity: Emplacement::class, inversedBy: 'deliveryRequestReferenceLines')]
    private ?Emplacement $targetLocationPicking = null;

    #[ORM\ManyToOne(targetEntity: Project::class)]
    private ?Project $project = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $commentaire = null;

    public function getId(): ?int {
        return $this->id;
    }

    public function getQuantityToPick(): ?int {
        return $this->quantityToPick;
    }

    public function setQuantityToPick(?int $quantityToPick): self {
        $this->quantityToPick = $quantityToPick;

        return $this;
    }

    public function getReference(): ?ReferenceArticle {
        return $this->reference;
    }

    public function setReference(?ReferenceArticle $reference): self {
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

    public function setRequest(?Demande $request): self {
        if($this->request && $this->request !== $request) {
            $this->request->removeReferenceLine($this);
        }

        $this->request = $request;

        if($request) {
            $request->addReferenceLine($this);
        }

        return $this;
    }

    public function getPickedQuantity(): ?int {
        return $this->pickedQuantity;
    }

    public function setPickedQuantity(?int $pickedQuantity): self {
        $this->pickedQuantity = $pickedQuantity;

        return $this;
    }

    public function getTargetLocationPicking(): ?Emplacement {
        return $this->targetLocationPicking;
    }

    public function setTargetLocationPicking(?Emplacement $targetLocationPicking): self {
        if($this->targetLocationPicking && $this->targetLocationPicking !== $targetLocationPicking) {
            $this->targetLocationPicking->removeDeliveryRequestReferenceLine($this);
        }

        $this->targetLocationPicking = $targetLocationPicking;

        if($targetLocationPicking) {
            $targetLocationPicking->addDeliveryRequestReferenceLine($this);
        }

        return $this;
    }

    public function getCommentaire(): ?string {
        return $this->commentaire;
    }

    public function setCommentaire(?string $commentaire): self {
        $this->commentaire = $commentaire;

        return $this;
    }

    public function getProject(): ?Project
    {
        return $this->project;
    }

    public function setProject(?Project $project): self
    {
        $this->project = $project;

        return $this;
    }

}
