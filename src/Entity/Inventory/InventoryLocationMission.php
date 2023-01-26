<?php

namespace App\Entity\Inventory;

use App\Entity\Emplacement;
use App\Repository\Inventory\InventoryMissionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: InventoryMissionRepository::class)]
class InventoryLocationMission {

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: InventoryMission::class, inversedBy: 'inventoryLocationMissions')]
    #[ORM\JoinColumn(nullable: false)]
    private ?InventoryMission $inventoryMission = null;

    #[ORM\ManyToOne(targetEntity: Emplacement::class, inversedBy: 'inventoryLocationMissions')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Emplacement $location = null;

    #[ORM\OneToMany(mappedBy: 'inventoryLocationMission', targetEntity: InventoryLocationMissionReferenceArticle::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Collection $inventoryLocationMissionReferenceArticles;

    #[ORM\Column(type: 'boolean', nullable: true)]
    private ?bool $done = null;

    public function __construct() {
        $this->inventoryLocationMissionReferenceArticles = new ArrayCollection();
    }

    public function getId(): ?int {
        return $this->id;
    }

    public function getInventoryMission(): ?InventoryMission {
        return $this->inventoryMission;
    }

    public function setInventoryMission(?InventoryMission $inventoryMission): self {
        if($this->inventoryMission && $this->inventoryMission !== $inventoryMission) {
            $this->inventoryMission->removeInventoryLocationMission($this);
        }
        $this->inventoryMission = $inventoryMission;
        $inventoryMission?->addInventoryLocationMission($this);

        return $this;
    }

    public function getLocation(): ?Emplacement {
        return $this->location;
    }

    public function setLocation(?Emplacement $location): self {
        if($this->location && $this->location !== $location) {
            $this->location->removeInventoryLocationMission($this);
        }
        $this->location = $location;
        $location?->addInventoryLocationMission($this);

        return $this;
    }

    public function getInventoryLocationMissionReferenceArticles(): Collection {
        return $this->inventoryLocationMissionReferenceArticles;
    }

    public function addInventoryLocationMissionReferenceArticle(InventoryLocationMissionReferenceArticle $inventoryLocationMissionReferenceArticle): self {
        if (!$this->inventoryLocationMissionReferenceArticles->contains($inventoryLocationMissionReferenceArticle)) {
            $this->inventoryLocationMissionReferenceArticles[] = $inventoryLocationMissionReferenceArticle;
            $inventoryLocationMissionReferenceArticle->setInventoryLocationMission($this);
        }

        return $this;
    }

    public function removeInventoryLocationMissionReferenceArticle(InventoryLocationMissionReferenceArticle $inventoryLocationMissionReferenceArticle): self {
        if ($this->inventoryLocationMissionReferenceArticles->removeElement($inventoryLocationMissionReferenceArticle)) {
            if ($inventoryLocationMissionReferenceArticle->getInventoryLocationMission() === $this) {
                $inventoryLocationMissionReferenceArticle->setInventoryLocationMission(null);
            }
        }

        return $this;
    }

    public function setInventoryLocationMissionReferenceArticles(?iterable $inventoryLocationMissionReferenceArticles): self {
        foreach($this->getInventoryLocationMissionReferenceArticles()->toArray() as $inventoryLocationMissionReferenceArticle) {
            $this->removeInventoryLocationMissionReferenceArticle($inventoryLocationMissionReferenceArticle);
        }

        $this->inventoryLocationMissionReferenceArticles = new ArrayCollection();
        foreach($inventoryLocationMissionReferenceArticles ?? [] as $inventoryLocationMissionReferenceArticle) {
            $this->addInventoryLocationMissionReferenceArticle($inventoryLocationMissionReferenceArticle);
        }

        return $this;
    }

    public function isDone(): ?bool {
        return $this->done;
    }

    public function setDone(bool $isDone): self {
        $this->done = $isDone;

        return $this;
    }

}
