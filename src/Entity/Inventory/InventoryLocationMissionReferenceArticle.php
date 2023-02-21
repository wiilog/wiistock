<?php

namespace App\Entity\Inventory;

use App\Entity\ReferenceArticle;
use App\Entity\Utilisateur;
use App\Repository\Inventory\InventoryMissionRepository;
use DateTime;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: InventoryMissionRepository::class)]
class InventoryLocationMissionReferenceArticle {

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: ReferenceArticle::class, inversedBy: 'inventoryLocationMissionReferenceArticles')]
    private ?ReferenceArticle $referenceArticle = null;

    #[ORM\ManyToOne(targetEntity: InventoryLocationMission::class, inversedBy: 'inventoryLocationMissionReferenceArticles')]
    private ?InventoryLocationMission $inventoryLocationMission = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?DateTime $scannedAt = null;

    #[ORM\ManyToOne(targetEntity: Utilisateur::class, inversedBy: 'inventoryLocationMissionReferenceArticles')]
    private ?Utilisateur $operator = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $percentage = null;

    public function __construct() {
    }

    public function getId(): ?int {
        return $this->id;
    }

    public function getReferenceArticle(): ?ReferenceArticle {
        return $this->referenceArticle;
    }

    public function setReferenceArticle(?ReferenceArticle $referenceArticle): self {
        if($this->referenceArticle && $this->referenceArticle !== $referenceArticle) {
            $this->referenceArticle->removeInventoryLocationMissionReferenceArticle($this);
        }
        $this->referenceArticle = $referenceArticle;
        $referenceArticle?->addInventoryLocationMissionReferenceArticle($this);

        return $this;
    }

    public function getInventoryLocationMission(): ?InventoryLocationMission {
        return $this->inventoryLocationMission;
    }

    public function setInventoryLocationMission(?InventoryLocationMission $inventoryLocationMission): self {
        if($this->inventoryLocationMission && $this->inventoryLocationMission !== $inventoryLocationMission) {
            $this->inventoryLocationMission->removeInventoryLocationMissionReferenceArticle($this);
        }
        $this->inventoryLocationMission = $inventoryLocationMission;
        $inventoryLocationMission?->addInventoryLocationMissionReferenceArticle($this);

        return $this;
    }

    public function getScannedAt(): ?DateTime {
        return $this->scannedAt;
    }

    public function setScannedAt(DateTime $scannedAt): self {
        $this->scannedAt = $scannedAt;

        return $this;
    }

    public function getOperator(): ?Utilisateur {
        return $this->operator;
    }

    public function setOperator(Utilisateur $operator): self {
        $this->operator = $operator;

        return $this;
    }

    public function getPercentage(): ?int {
        return $this->percentage;
    }

    public function setPercentage(int $percentage): self {
        $this->percentage = $percentage;

        return $this;
    }

}
