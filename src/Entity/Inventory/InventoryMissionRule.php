<?php

namespace App\Entity\Inventory;

use App\Repository\Inventory\InventoryMissionRuleRepository;
use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: InventoryMissionRuleRepository::class)]
class InventoryMissionRule {

    public const WEEKS = "weeks";
    public const MONTHS = "months";

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    private ?string $label = null;

    #[ORM\ManyToMany(targetEntity: InventoryCategory::class)]
    private Collection $categories;

    #[ORM\Column(type: 'integer')]
    private ?int $periodicity = null;

    #[ORM\Column(type: 'string', length: 255)]
    private ?string $periodicityUnit = null;

    #[ORM\Column(type: 'integer')]
    private ?int $duration = null;

    #[ORM\Column(type: 'string', length: 255)]
    private ?string $durationUnit = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?DateTime $lastRun = null;

    #[ORM\OneToMany(mappedBy: 'creator', targetEntity: InventoryMission::class)]
    private Collection $createdMissions;

    public function __construct() {
        $this->categories = new ArrayCollection();
        $this->createdMissions = new ArrayCollection();
    }

    public function __toString(): string {
        return $this->getLabel();
    }

    public function getId(): ?int {
        return $this->id;
    }

    public function getLabel(): ?string {
        return $this->label;
    }

    public function setLabel(string $label): self {
        $this->label = $label;

        return $this;
    }

    /**
     * @return Collection<int, InventoryCategory>
     */
    public function getCategories(): Collection {
        return $this->categories;
    }

    public function addCategory(InventoryCategory $category): self {
        if (!$this->categories->contains($category)) {
            $this->categories[] = $category;
        }

        return $this;
    }

    public function removeCategory(InventoryCategory $category): self {
        $this->categories->removeElement($category);

        return $this;
    }

    public function setCategories(?iterable $categories): self {
        foreach($this->getCategories() as $category) {
            $this->removeCategory($category);
        }

        foreach($categories ?? [] as $category) {
            $this->addCategory($category);
        }

        return $this;
    }

    public function getPeriodicity(): ?int {
        return $this->periodicity;
    }

    public function setPeriodicity(int $periodicity): self {
        $this->periodicity = $periodicity;

        return $this;
    }

    public function getPeriodicityUnit(): ?string {
        return $this->periodicityUnit;
    }

    public function setPeriodicityUnit(string $periodicityUnit): self {
        $this->periodicityUnit = $periodicityUnit;

        return $this;
    }

    public function getDuration(): ?int {
        return $this->duration;
    }

    public function setDuration(int $duration): self {
        $this->duration = $duration;

        return $this;
    }

    public function getDurationUnit(): ?string {
        return $this->durationUnit;
    }

    public function setDurationUnit(string $durationUnit): self {
        $this->durationUnit = $durationUnit;

        return $this;
    }

    public function getLastRun(): ?DateTime {
        return $this->lastRun;
    }

    public function setLastRun(?DateTime $lastRun): self {
        $this->lastRun = $lastRun;
        return $this;
    }

    /**
     * @return Collection<int, InventoryMission>
     */
    public function getCreatedMissions(): Collection
    {
        return $this->createdMissions;
    }

    public function addCreatedMission(InventoryMission $createdMission): self
    {
        if (!$this->createdMissions->contains($createdMission)) {
            $this->createdMissions[] = $createdMission;
            $createdMission->setCreator($this);
        }

        return $this;
    }

    public function removeCreatedMission(InventoryMission $createdMission): self
    {
        if ($this->createdMissions->removeElement($createdMission)) {
            // set the owning side to null (unless already changed)
            if ($createdMission->getCreator() === $this) {
                $createdMission->setCreator(null);
            }
        }

        return $this;
    }

}
