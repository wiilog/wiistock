<?php

namespace App\Entity\Inventory;

use App\Entity\Emplacement;
use App\Entity\ScheduleRule;
use App\Entity\Utilisateur;
use App\Repository\Inventory\InventoryMissionRuleRepository;
use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: InventoryMissionRuleRepository::class)]
class InventoryMissionRule extends ScheduleRule
{
    public const DURATION_UNIT_DAYS = "days";
    public const DURATION_UNIT_WEEKS = "weeks";
    public const DURATION_UNIT_MONTHS = "months";

    public const DURATION_UNITS = [
        self::DURATION_UNIT_DAYS,
        self::DURATION_UNIT_WEEKS,
        self::DURATION_UNIT_MONTHS,
    ];

    public const DURATION_UNITS_LABELS = [
        self::DURATION_UNIT_DAYS => "jour(s)",
        self::DURATION_UNIT_WEEKS => "semaine(s)",
        self::DURATION_UNIT_MONTHS => "mois",
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

    #[ORM\ManyToOne(targetEntity: Utilisateur::class)]
    private ?Utilisateur $requester = null;

    #[ORM\Column(type: "boolean", nullable: false, options: ["default" => true])]
    private ?bool $active;

    public function __construct() {
        $this->categories = new ArrayCollection();
        $this->createdMissions = new ArrayCollection();
        $this->locations =  new ArrayCollection();
        $this->active = true;
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
    public function setMissionType(?string $missionType): self
    {
        $this->missionType = $missionType;

        return $this;
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

    /**
     * @return Utilisateur|null
     */
    public function getRequester(): ?Utilisateur
    {
        return $this->requester;
    }

    /**
     * @param Utilisateur|null $requester
     */
    public function setRequester(?Utilisateur $requester): self
    {
        $this->requester = $requester;

        return $this;

    }

    public function isActive(): ?bool {
        return $this->active;
    }

    public function setActive(?bool $active): self {
        $this->active = $active;

        return $this;
    }

}
