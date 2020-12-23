<?php

namespace App\Entity\Dashboard\Meter;

use Doctrine\ORM\Mapping as ORM;
use App\Entity\Dashboard;
use App\Repository\Dashboard as DashboardRepository;

/**
 * @ORM\Entity(repositoryClass=DashboardRepository\ChartMeterRepository::class)
 * @ORM\Table(name="dashboard_meter_chart")
 */
class Chart
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="json")
     */
    private $data = [];

    /**
     * @ORM\Column(type="string", nullable=true)
     */
    private $total;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $location;

    /**
     * @ORM\Column(type="json")
     */
    private $chartColors = [];

    /**
     * @var Dashboard\Component
     * @ORM\ManyToOne(targetEntity=Dashboard\Component::class, inversedBy="chartMeters")
     */
    private $component;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getData(): ?array
    {
        return array_reduce($this->data, function (array $carry, $item) {
            $carry[$item['dataKey']] = $item['data'];
            return $carry;
        }, []);
    }

    public function setData(array $data): self
    {

        $savedData = [];
        foreach ($data as $key => $datum) {
            $savedData[] = [
                'dataKey' => $key,
                'data' => $datum
            ];
        }
        $this->data = $savedData;


        return $this;
    }

    public function getTotal(): ?string
    {
        return $this->total;
    }

    public function setTotal(?string $total): self
    {
        $this->total = $total;

        return $this;
    }

    public function getLocation(): ?string
    {
        return $this->location;
    }

    public function setLocation(?string $location): self
    {
        $this->location = $location;

        return $this;
    }

    public function getChartColors(): ?array
    {
        return $this->chartColors;
    }

    public function setChartColors(array $chartColors): self
    {
        $this->chartColors = $chartColors;

        return $this;
    }

    /**
     * @return Dashboard\Component|null
     */
    public function getComponent(): ?Dashboard\Component {
        return $this->component;
    }

    /**
     * @param Dashboard\Component|null $component
     * @return self
     */
    public function setComponent(?Dashboard\Component $component): self {

        if ($this->component) {
            $this->component->setMeter(null);
        }

        $this->component = $component;

        if ($this->component) {
            $this->component->setMeter($this);
        }

        return $this;
    }

}
