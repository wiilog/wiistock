<?php

namespace App\Entity\Dashboard;

use App\Entity\LocationCluster;
use App\Repository\Dashboard as DashboardRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\Dashboard\Meter as DashboardMeter;
use phpDocumentor\Reflection\Types\Collection;

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
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity=ComponentType::class, inversedBy="componentsUsing")
     * @ORM\JoinColumn(nullable=false)
     */
    private $type;

    /**
     * @ORM\ManyToOne(targetEntity=PageRow::class, inversedBy="components")
     * @ORM\JoinColumn(nullable=false)
     */
    private $row;

    /**
     * @ORM\Column(type="integer")
     */
    private $columnIndex;

    /**
     * @ORM\Column(type="json")
     */
    private $config = [];

    /**
     * @var null|DashboardMeter\Indicator;
     * @ORM\OneToOne (targetEntity=DashboardMeter\Indicator::class, mappedBy="component", cascade={"remove"})
     */
    private $indicatorMeter;

    /**
     * @var null|DashboardMeter\Chart;
     * @ORM\OneToOne(targetEntity=DashboardMeter\Chart::class, mappedBy="component", cascade={"remove"})
     */
    private $chartMeter;

    /**
     * @var Collection;
     * @ORM\OneToMany(targetEntity=LocationCluster::class, mappedBy="component", cascade={"remove"})
     */
    private $locationClusters;

    /**
     * Component constructor.
     */
    public function __construct()
    {
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

    public function getLocationClusters() : Collection {
        return $this->locationClusters;
    }

    public function getLocationCluster(string $clusterKey) : ?LocationCluster {
        $filteredClusters = $this->locationClusters->filter(function (LocationCluster $locationCluster) use ($clusterKey) {
            return $locationCluster->getClusterKey() === $clusterKey;
        });
        return !$filteredClusters->isEmpty() ? $filteredClusters->first() : null;
    }

    public function addLocationCluster(LocationCluster $locationCluster) {
        if (!$this->locationClusters->contains($locationCluster)) {
            $this->locationClusters[] = $locationCluster;
        }
    }

    public function removeLocationCluster(LocationCluster $locationCluster): self
    {
        if ($this->locationClusters->contains($locationCluster)) {
            $this->locationClusters->removeElement($locationCluster);
        }
        return $this;
    }

}
