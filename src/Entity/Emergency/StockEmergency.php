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

    #[ORM\Column(type: TYPES::STRING, length: 255, nullable: false, enumType: EmergencyTriggerEnum::class)]
    private ?string $emergencyTrigger = null;

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
    #[ORM\ManyToMany(targetEntity: ReceptionReferenceArticle::class, mappedBy: 'stockEmergency')]
    private Collection $receptionReferenceArticles;

    public function __construct() {
        $this->receptionReferenceArticles = new ArrayCollection();
    }

    public function getEmergencyTrigger(): ?string {
        return $this->emergencyTrigger;
    }

    public function setEmergencyTrigger(string $emergencyTrigger): self {
        $this->emergencyTrigger = $emergencyTrigger;

        return $this;
    }

    /**
     * @return Collection<int, ReferenceArticle>
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
