<?php

namespace App\Entity\Inventory;

use App\Entity\Emplacement;
use App\Entity\Utilisateur;
use App\Repository\Inventory\InventoryMissionRuleRepository;
use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: InventoryMissionRuleRepository::class)]
class InventoryMissionRule {

    public const ONCE = 'once-frequency';
    public const HOURLY = 'hourly-frequency';
    public const DAILY = 'every-day-frequency';
    public const WEEKLY = 'every-week-frequency';
    public const MONTHLY = 'every-month-frequency';

    public const WEEKS = "weeks";
    public const MONTHS = "months";

    public const LAST_DAY_OF_WEEK = 'last';

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

    #[ORM\Column(type: "string")]
    private ?string $missionType = null;

    #[ORM\Column(type: "text")]
    private ?string $description = null;

    #[ORM\ManyToMany(targetEntity: Emplacement::class, mappedBy: 'inventoryMissionRules')]
    private Collection $locations;

    #[ORM\Column(type: "datetime", nullable: false)]
    private ?DateTime $begin = null;

    #[ORM\Column(type: "string", length: 255, nullable: true)]
    //For the "daily" and "weekly" scheduled inventories
    private ?string $frequency = null;

    #[ORM\Column(type: "integer", length: 255, nullable: true)]
    //For the "daily" and "weekly" scheduled inventories
    private ?int $period = null;

    #[ORM\Column(type: "string", length: 255, nullable: true)]
    //For the "hourly" frequency when the hours or minutes were chosen
    private ?string $intervalTime = null;

    #[ORM\Column(type: "integer", length: 255, nullable: true)]
    //For the "hourly" frequency when the hours or minutes were chosen
    private ?int $intervalPeriod = null;

    #[ORM\Column(type: "json", length: 255, nullable: true)]
    //Only for the "weekly" scheduled inventories
    private ?array $weekDays = null;

    #[ORM\Column(type: "json", length: 255, nullable: true)]
    //Only for the "month" scheduled inventories
    private ?array $monthDays = null;

    #[ORM\Column(type: "json", length: 255, nullable: true)]
    //Only for the "month" scheduled inventories
    private ?array $months = null;

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

    /**
     * @return DateTime|null
     */
    public function getBegin(): ?DateTime
    {
        return $this->begin;
    }

    /**
     * @param DateTime|null $begin
     */
    public function setBegin(?DateTime $begin): void
    {
        $this->begin = $begin;
    }

    /**
     * @return string|null
     */
    public function getFrequency(): ?string
    {
        return $this->frequency;
    }

    /**
     * @param string|null $frequency
     */
    public function setFrequency(?string $frequency): void
    {
        $this->frequency = $frequency;
    }

    /**
     * @return int|null
     */
    public function getPeriod(): ?int
    {
        return $this->period;
    }

    /**
     * @param int|null $period
     */
    public function setPeriod(?int $period): void
    {
        $this->period = $period;
    }

    /**
     * @return string|null
     */
    public function getIntervalTime(): ?string
    {
        return $this->intervalTime;
    }

    /**
     * @param string|null $intervalTime
     */
    public function setIntervalTime(?string $intervalTime): void
    {
        $this->intervalTime = $intervalTime;
    }

    /**
     * @return int|null
     */
    public function getIntervalPeriod(): ?int
    {
        return $this->intervalPeriod;
    }

    /**
     * @param int|null $intervalPeriod
     */
    public function setIntervalPeriod(?int $intervalPeriod): void
    {
        $this->intervalPeriod = $intervalPeriod;
    }

    /**
     * @return array|null
     */
    public function getWeekDays(): ?array
    {
        return $this->weekDays;
    }

    /**
     * @param array|null $weekDays
     */
    public function setWeekDays(?array $weekDays): void
    {
        $this->weekDays = $weekDays;
    }

    /**
     * @return array|null
     */
    public function getMonthDays(): ?array
    {
        return $this->monthDays;
    }

    /**
     * @param array|null $monthDays
     */
    public function setMonthDays(?array $monthDays): void
    {
        $this->monthDays = $monthDays;
    }

    /**
     * @return array|null
     */
    public function getMonths(): ?array
    {
        return $this->months;
    }

    /**
     * @param array|null $months
     */
    public function setMonths(?array $months): void
    {
        $this->months = $months;
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
    public function setDescription(?string $description): void
    {
        $this->description = $description;
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
