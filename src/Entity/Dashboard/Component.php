<?php

namespace App\Entity\Dashboard;

use App\Repository\Dashboard as DashboardRepository;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\Dashboard\Meter as DashboardMeter;

/**
 * @ORM\Entity(repositoryClass=DashboardRepository\ComponentRepository::class)
 * @ORM\Table(name="dashboard_component",
 *    uniqueConstraints={
 *        @ORM\UniqueConstraint(name="component_row_unique", columns={"column_index", "row_id"})
 *    }
 * )
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
     * @ORM\OneToOne (targetEntity=DashboardMeter\Indicator::class, mappedBy="component")
     */
    private $indicatorMeter;

    /**
     * @var null|DashboardMeter\Chart;
     * @ORM\OneToOne(targetEntity=DashboardMeter\Chart::class, mappedBy="component")
     */
    private $chartMeter;

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
        $this->row = $row;

        return $this;
    }

    /**
     * @return DashboardMeter\Indicator|DashboardMeter\Chart|null
     */
    public function getMeter() {
        return isset($this->indicatorMeter)
            ? $this->indicatorMeter
            : $this->chartMeter;
    }

    /**
     * @param DashboardMeter\Indicator|DashboardMeter\Chart|null $meter
     * @return Component
     */
    public function setMeter($meter): self {
        if ($meter instanceof DashboardMeter\Indicator) {
            $this->indicatorMeter = $meter;
        }
        else if ($meter instanceof DashboardMeter\Chart) {
            $this->chartMeter = $meter;
        }
        else if (!isset($meter)) {
            $this->indicatorMeter = null;
            $this->chartMeter = null;
        }
        return $this;
    }

}
