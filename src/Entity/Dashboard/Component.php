<?php

namespace App\Entity\Dashboard;

use App\Entity\LocationCluster;
use App\Repository\Dashboard as DashboardRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\Dashboard\Meter as DashboardMeter;

/**
 * @ORM\Entity(repositoryClass=DashboardRepository\ComponentRepository::class)
 * @ORM\Table(name="dashboard_component")
 */
class Component
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private ?int $id = null;

    /**
     * @ORM\ManyToOne(targetEntity=ComponentType::class, inversedBy="componentsUsing")
     * @ORM\JoinColumn(nullable=false)
     */
    private ?ComponentType $type = null;

    /**
     * @ORM\ManyToOne(targetEntity=PageRow::class, inversedBy="components")
     * @ORM\JoinColumn(nullable=false)
     */
    private ?PageRow $row = null;

    /**
     * @ORM\Column(type="integer")
     */
    private ?int $columnIndex = null;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private ?int $direction = null;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private ?int $cellIndex = null;

    /**
     * @ORM\Column(type="json")
     */
    private array $config = [];

    /**
     * @ORM\OneToOne (targetEntity=DashboardMeter\Indicator::class, mappedBy="component", cascade={"remove"})
     */
    private ?DashboardMeter\Indicator $indicatorMeter = null;

    /**
     * @ORM\OneToOne(targetEntity=DashboardMeter\Chart::class, mappedBy="component", cascade={"remove"})
     */
    private ?DashboardMeter\Chart $chartMeter = null;

    /**
     * @ORM\OneToMany(targetEntity=LocationCluster::class, mappedBy="component", cascade={"remove"})
     */
    private Collection $locationClusters;

    /**
     * Component constructor.
     */
    public function __construct() {
        $this->locationClusters = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getType(): ?ComponentType
    {
        return $this->type;
    }

    public function setType(?ComponentType $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function getColumnIndex(): ?int
    {
        return $this->columnIndex;
    }

    public function setColumnIndex(int $columnIndex): self
    {
        $this->columnIndex = $columnIndex;

        return $this;
    }

    public function getDirection(): ?int
    {
        return $this->direction;
    }

    public function setDirection(?int $direction): self
    {
        $this->direction = $direction;

        return $this;
    }

    public function getCellIndex(): ?int
    {
        return $this->cellIndex;
    }

    public function setCellIndex(?int $cellIndex): self
    {
        $this->cellIndex = $cellIndex;

        return $this;
    }

    public function getConfig(): ?array
    {
        return $this->config;
    }

    public function setConfig(array $config): self
    {
        $this->config = $config;

        return $this;
    }

    public function getRow(): ?PageRow
    {
        return $this->row;
    }

    public function setRow(?PageRow $row): self
    {
        $row->addComponent($this);
        $this->row = $row;

        return $this;
    }

    /**
     * @return DashboardMeter\Indicator|DashboardMeter\Chart|null
     */
    public function getMeter()
    {
        return isset($this->indicatorMeter)
            ? $this->indicatorMeter
            : $this->chartMeter;
    }

    /**
     * @param DashboardMeter\Indicator|DashboardMeter\Chart|null $meter
     * @return Component
     */
    public function setMeter($meter): self
    {
        if ($meter instanceof DashboardMeter\Indicator) {
            $this->indicatorMeter = $meter;
        } else if ($meter instanceof DashboardMeter\Chart) {
            $this->chartMeter = $meter;
        } else if (!isset($meter)) {
            $this->indicatorMeter = null;
            $this->chartMeter = null;
        }
        return $this;
    }

    public function getLocationClusters(): Collection {
        return $this->locationClusters;
    }

    public function getLocationCluster(string $clusterKey): ?LocationCluster {
        $filteredClusters = $this->locationClusters->filter(function (LocationCluster $locationCluster) use ($clusterKey) {
            return $locationCluster->getClusterKey() === $clusterKey;
        });
        return !$filteredClusters->isEmpty() ? $filteredClusters->first() : null;
    }

    public function addLocationCluster(LocationCluster $locationCluster): self {
        if (!$this->locationClusters->contains($locationCluster)) {
            $this->locationClusters[] = $locationCluster;
        }

        return $this;
    }

    public function removeLocationCluster(LocationCluster $locationCluster): self
    {
        if ($this->locationClusters->contains($locationCluster)) {
            $this->locationClusters->removeElement($locationCluster);
        }
        return $this;
    }

}
