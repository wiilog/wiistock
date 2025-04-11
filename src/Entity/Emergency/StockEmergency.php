<?php

namespace App\Entity\Emergency;

use App\Entity\Emplacement;
use App\Entity\ReceptionReferenceArticle;
use App\Entity\ReferenceArticle;
use App\Repository\Emergency\StockEmergencyRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: StockEmergencyRepository::class)]
#[ORM\Index(fields: ["emergencyTrigger"])]
class StockEmergency extends Emergency {

    #[ORM\Column(type: Types::STRING, nullable: false, enumType: EmergencyTriggerEnum::class)]
    private ?EmergencyTriggerEnum $emergencyTrigger = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $expectedQuantity = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $alreadyReceivedQuantity = null;

    #[ORM\ManyToOne(targetEntity: Emplacement::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Emplacement $expectedLocation = null;

    /**
     * @var Collection<int, ReceptionReferenceArticle>
     */
    #[ORM\ManyToMany(targetEntity: ReceptionReferenceArticle::class, mappedBy: 'stockEmergencies')]
    private Collection $receptionReferenceArticles;

    public function __construct() {
        $this->receptionReferenceArticles = new ArrayCollection();
    }

    public function getEmergencyTrigger(): ?EmergencyTriggerEnum {
        return $this->emergencyTrigger;
    }

    public function setEmergencyTrigger(?EmergencyTriggerEnum $emergencyTrigger): self {
        $this->emergencyTrigger = $emergencyTrigger;

        return $this;
    }

    /**
     * @return Collection<int, ReceptionReferenceArticle>
     */
    public function getReceptionReferenceArticle(): Collection {
        return $this->receptionReferenceArticles;
    }

    public function addReceptionReferenceArticle(ReceptionReferenceArticle $receptionReferenceArticle): self {
        if (!$this->receptionReferenceArticles->contains($receptionReferenceArticle)) {
            $this->receptionReferenceArticles[] = $receptionReferenceArticle;
        }

        return $this;
    }

    public function removeReceptionReferenceArticle(ReceptionReferenceArticle $receptionReferenceArticle): self {
        $this->receptionReferenceArticles->removeElement($receptionReferenceArticle);

        return $this;
    }

    public function getExpectedQuantity(): ?int {
        return $this->expectedQuantity;
    }

    public function setExpectedQuantity(?int $expectedQuantity): self {
        $this->expectedQuantity = $expectedQuantity;

        return $this;
    }

    public function getAlreadyReceivedQuantity(): ?int {
        return $this->alreadyReceivedQuantity;
    }

    public function setAlreadyReceivedQuantity(?int $alreadyReceivedQuantity): self {
        $this->alreadyReceivedQuantity = $alreadyReceivedQuantity;

        return $this;
    }

    public function getExpectedLocation(): ?Emplacement {
        return $this->expectedLocation;
    }

    public function setExpectedLocation(?Emplacement $expectedLocation): self {
        $this->expectedLocation = $expectedLocation;

        return $this;
    }
}
