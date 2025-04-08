<?php

namespace App\Entity\Emergency;

use App\Entity\Emplacement;
use App\Entity\Fournisseur;
use App\Entity\Reception;
use App\Entity\ReceptionReferenceArticle;
use App\Entity\ReferenceArticle;
use App\Repository\StockEmergencyRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: StockEmergencyRepository::class)]
class StockEmergency extends Emergency
{
    #[ORM\Column(type: TYPES::STRING, length: 255)]
    private ?string $emergencyTrigger = null;

    /**
     * @var Collection<int, ReferenceArticle>
     */
    #[ORM\OneToMany(mappedBy: 'stockEmergency', targetEntity: ReceptionReferenceArticle::class)]
    private Collection $receptionReferenceArticle;

    #[ORM\OneToOne(targetEntity: Reception::class)]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?Reception $lastReception = null;

    #[ORM\ManyToOne(targetEntity: Reception::class)]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?ReferenceArticle $reference = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $quantity = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $quantityAlreadyReceived = null;

    #[ORM\ManyToOne(targetEntity: Emplacement::class, inversedBy: 'stockEmergencies')]
    private ?Emplacement $location = null;

    public function __construct()
    {
        $this->receptionReferenceArticle = new ArrayCollection();
    }

    public function getEmergencyTrigger(): ?string
    {
        return $this->emergencyTrigger;
    }

    public function setEmergencyTrigger(string $emergencyTrigger): self
    {
        $this->emergencyTrigger = $emergencyTrigger;

        return $this;
    }

    /**
     * @return Collection<int, ReferenceArticle>
     */
    public function getReceptionReferenceArticle(): Collection
    {
        return $this->receptionReferenceArticle;
    }

    public function addReceptionReferenceArticle(ReceptionReferenceArticle $receptionReferenceArticle): self
    {
        if (!$this->receptionReferenceArticle->contains($receptionReferenceArticle)) {
            $this->receptionReferenceArticle[] = $receptionReferenceArticle;
            $receptionReferenceArticle->setStockEmergency($this);
        }

        return $this;
    }

    public function removeReceptionReferenceArticle(ReceptionReferenceArticle $receptionReferenceArticle): self
    {
        if ($this->receptionReferenceArticle->removeElement($receptionReferenceArticle)) {
            // set the owning side to null (unless already changed)
            if ($receptionReferenceArticle->getStockEmergency() === $this) {
                $receptionReferenceArticle->setStockEmergency(null);
            }
        }

        return $this;
    }

    public function getLastReception(): ?Reception
    {
        return $this->lastReception;
    }

    public function setLastReception(?Reception $lastReception): self
    {
        $this->lastReception = $lastReception;

        return $this;
    }

    public function getReference(): ?ReferenceArticle
    {
        return $this->reference;
    }

    public function setReference(?ReferenceArticle $reference): self
    {
        $this->reference = $reference;

        return $this;
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

    public function getQuantityAlreadyReceived(): ?int
    {
        return $this->quantityAlreadyReceived;
    }

    public function setQuantityAlreadyReceived(?int $quantityAlreadyReceived): self
    {
        $this->quantityAlreadyReceived = $quantityAlreadyReceived;

        return $this;
    }

    public function getLocation(): ?Emplacement
    {
        return $this->location;
    }

    public function setLocation(?Emplacement $location): self
    {
        $this->location = $location;

        return $this;
    }
}
