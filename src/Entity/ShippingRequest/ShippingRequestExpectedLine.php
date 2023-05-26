<?php

namespace App\Entity\ShippingRequest;

use App\Entity\ReferenceArticle;
use App\Repository\ShippingRequest\ShippingRequestExpectedLineRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
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

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 3)]
    private ?float $unitPrice = null;

    #[ORM\Column(type: Types::FLOAT)]
    private ?float $unitWeight = null;

    /* Line price, calculated on line adding or removing */
    #[ORM\Column(type: Types::FLOAT, nullable: true)]
    private ?float $totalPrice = null;

    #[ORM\ManyToOne(targetEntity: ReferenceArticle::class)]
    private ?ReferenceArticle $referenceArticle = null;

    #[ORM\ManyToOne(targetEntity: ShippingRequest::class, inversedBy: 'expectedLines')]
    private ?ShippingRequest $request = null;

    #[ORM\OneToMany(mappedBy: 'expectedLine', targetEntity: ShippingRequestLine::class)]
    private Collection $lines;

    public function __construct() {
        $this->lines = new ArrayCollection();
    }

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

    public function getUnitPrice(): ?float {
        return $this->unitPrice;
    }

    public function setUnitPrice(?float $unitPrice): self {
        $this->unitPrice = $unitPrice;
        return $this;
    }

    public function getUnitWeight(): ?float {
        return $this->unitWeight;
    }

    public function setUnitWeight(?float $unitWeight): self {
        $this->unitWeight = $unitWeight;
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


    public function getLines(): Collection {
        return $this->lines;
    }

    public function addLine(ShippingRequestLine $line): self {
        if (!$this->lines->contains($line)) {
            $this->lines[] = $line;
            $line->setExpectedLine($this);
        }

        return $this;
    }

    public function removeLine(ShippingRequestLine $line): self {
        if ($this->lines->removeElement($line)) {
            if ($line->getExpectedLine() === $this) {
                $line->setExpectedLine(null);
            }
        }

        return $this;
    }

    public function getTotalPrice(): ?float {
        return $this->totalPrice;
    }

    public function setTotalPrice(?float $totalPrice): self {
        $this->totalPrice = $totalPrice;
        return $this;
    }

}
