<?php

namespace App\Entity\Inventory;

use App\Entity\Emplacement;
use App\Entity\Interfaces\Frequency;
use App\Entity\Traits\FrequencyTrait;
use App\Entity\Utilisateur;
use App\Repository\Inventory\InventoryMissionRuleRepository;
use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: InventoryMissionRuleRepository::class)]
class InventoryMissionRule implements Frequency {

    use FrequencyTrait;

    public const WEEKS = "weeks";
    public const MONTHS = "months";

    public const DURATION_UNITS = [
        self::WEEKS,
        self::MONTHS,
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    private ?string $label = null;

    #[ORM\ManyToMany(targetEntity: InventoryCategory::class)]
    #[ORM\JoinTable(name: 'inventory_mission_rule_inventory_category')]
    private Collection $categories;

    #[ORM\Column(type: 'integer')]
    private ?int $duration = null;

    #[ORM\Column(type: 'string', length: 255)]
    private ?string $durationUnit = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?DateTime $lastRun = null;

    #[ORM\OneToMany(mappedBy: 'creator', targetEntity: InventoryMission::class)]
    private Collection $createdMissions;

    #[ORM\Column(type: "string")]
    private ?string $missionType = null;

    #[ORM\Column(type: "text")]
    private ?string $description = null;

    #[ORM\ManyToMany(targetEntity: Emplacement::class, inversedBy: 'inventoryMissionRules')]
    private Collection $locations;

    #[ORM\ManyToOne(targetEntity: Utilisateur::class)]
    private ?Utilisateur $creator = null;

    public function __construct() {
        $this->categories = new ArrayCollection();
        $this->createdMissions = new ArrayCollection();
        $this->locations =  new ArrayCollection();
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

    /**
     * @return string|null
     */
    public function getMissionType(): ?string
    {
        return $this->missionType;
    }

    /**
     * @param string|null $missionType
     */
    public function setMissionType(?string $missionType): void
    {
        $this->missionType = $missionType;
    }

    /**
     * @return string|null
     */
    public function getDescription(): ?string
    {
        return $this->description;
    }

    /**
     * @param string|null $description
     */
    public function setDescription(?string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function getLocations(): Collection {
        return $this->locations;
    }

    public function addLocation(Emplacement $location): self {
        if (!$this->locations->contains($location)) {
            $this->locations[] = $location;
            $location->addInventoryMissionRule($this);
        }

        return $this;
    }

    public function removeLocation(Emplacement $location): self {
        if ($this->locations->removeElement($location)) {
            $location->removeInventoryMissionRule($this);
        }

        return $this;
    }

    public function setLocations(?iterable $locations): self {
        foreach($this->getLocations()->toArray() as $location) {
            $this->removeLocation($location);
        }

        $this->locations = new ArrayCollection();
        foreach($locations ?? [] as $location) {
            $this->addLocation($location);
        }

        return $this;
    }

    /**
     * @return Utilisateur|null
     */
    public function getCreator(): ?Utilisateur
    {
        return $this->creator;
    }

    /**
     * @param Utilisateur|null $creator
     */
    public function setCreator(?Utilisateur $creator): self
    {
        $this->creator = $creator;

        return $this;

    }
}
