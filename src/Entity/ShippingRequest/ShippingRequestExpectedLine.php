<?php

namespace App\Entity\ShippingRequest;

use App\Entity\ReferenceArticle;
use App\Entity\Utilisateur;
use App\Repository\ShippingRequest\ShippingRequestExpectedLineRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ShippingRequestExpectedLineRepository::class)]
class ShippingRequestExpectedLine {

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(type: Types::INTEGER)]
    private ?int $quantity = null;

    #[ORM\Column(type: Types::FLOAT)]
    private ?float $price = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 3)]
    private ?float $weight = null;

    #[ORM\ManyToOne(targetEntity: ReferenceArticle::class)]
    private ?ReferenceArticle $referenceArticle = null;

    #[ORM\ManyToOne(targetEntity: ShippingRequest::class, inversedBy: 'expectedLines')]
    private ?ShippingRequest $request = null;

    public function getId(): ?int {
        return $this->id;
    }

    public function getReferenceArticle(): ?ReferenceArticle {
        return $this->referenceArticle;
    }

    public function setReferenceArticle(?ReferenceArticle $referenceArticle): self {
        $this->referenceArticle = $referenceArticle;
        return $this;
    }

    public function getQuantity(): ?int {
        return $this->quantity;
    }

    public function setQuantity(?int $quantity): self {
        $this->quantity = $quantity;
        return $this;
    }

    public function getPrice(): ?float {
        return $this->price;
    }

    public function setPrice(?float $price): self {
        $this->price = $price;
        return $this;
    }

    public function getWeight(): ?float {
        return $this->weight;
    }

    public function setWeight(?float $weight): self {
        $this->weight = $weight;
        return $this;
    }

    public function getRequest(): ?ShippingRequest {
        return $this->request;
    }

    public function setRequest(?ShippingRequest $request): self {
        if($this->request && $this->request !== $request) {
            $this->request->removeExpectedLine($this);
        }
        $this->request = $request;
        $request?->addExpectedLine($this);

        return $this;
    }

}
