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
    private ?string $unitPrice = null;

    #[ORM\Column(type: Types::FLOAT)]
    private ?float $unitWeight = null;

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
        return isset($this->unitPrice)
            ? ((float) $this->unitPrice)
            : null;
    }

    public function setUnitPrice(?float $unitPrice): self {
        $this->unitPrice = isset($unitPrice)
            ? ((string) $unitPrice)
            : null;
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

}
