<?php

namespace App\Entity\Emergency;

use App\Entity\Arrivage;
use App\Repository\TrackingEmergencyRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TrackingEmergencyRepository::class)]
class TrackingEmergency extends Emergency
{
    /**
     * @var Collection<int, Arrivage>
     */
    #[ORM\OneToMany(mappedBy: 'trackingEmergency', targetEntity: Arrivage::class)]
    private Collection $arrivals;

    #[ORM\ManyToOne(targetEntity: Arrivage::class, inversedBy: 'emergencies')]
    private ?Arrivage $lastArrival = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $postNumber = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $internalArticleCode = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $supplierArticleCode = null;

    public function __construct() {
        $this->arrivals = new ArrayCollection();
    }

    /**
     * @return Collection<int, Arrivage>
     */
    public function getArrivals(): Collection {
        return $this->arrivals;
    }

    public function addArrival(Arrivage $arrival): self {
        if (!$this->arrivals->contains($arrival)) {
            $this->arrivals[] = $arrival;
            $arrival->setTrackingEmergency($this);
        }

        return $this;
    }

    public function removeArrival(Arrivage $arrival): self {
        if ($this->arrivals->removeElement($arrival)) {
            // set the owning side to null (unless already changed)
            if ($arrival->getTrackingEmergency() === $this) {
                $arrival->setTrackingEmergency(null);
            }
        }

        return $this;
    }

    public function setArrivals(?iterable $arrivals): self {
        foreach ($this->getArrivals()->toArray() as $arrival){
            $this->removeArrival($arrival);
        }

        $this->arrivals = new ArrayCollection();
        foreach ($arrivals ?? [] as $arrival){
            $this->addArrival($arrival);
        }

        return $this;
    }

    public function getLastArrival(): ?Arrivage {
        return $this->lastArrival;
    }

    public function setLastArrival(?Arrivage $lastArrival): self {
        $this->lastArrival = $lastArrival;

        return $this;
    }

    public function getPostNumber(): ?string {
        return $this->postNumber;
    }

    public function setPostNumber(?string $postNb): self {
        $this->postNumber = $postNb;

        return $this;
    }

    public function getInternalArticleCode(): ?string
    {
        return $this->internalArticleCode;
    }

    public function setInternalArticleCode(?string $internalArticleCode): self
    {
        $this->internalArticleCode = $internalArticleCode;

        return $this;
    }

    public function getSupplierArticleCode(): ?string
    {
        return $this->supplierArticleCode;
    }

    public function setSupplierArticleCode(?string $supplierArticleCode): self
    {
        $this->supplierArticleCode = $supplierArticleCode;

        return $this;
    }
}
