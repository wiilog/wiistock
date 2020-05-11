<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\DashboardChartMeterRepository")
 */
class DashboardChartMeter
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @var string
     * @ORM\Column(type="string", length=255)
     */
    private $chartKey;


    /**
     * @ORM\Column(type="string", length=255)
     */
    private $dashboard;

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

    public function getId(): ?int
    {
        return $this->id;
    }


    public function getDashboard(): ?string
    {
        return $this->dashboard;
    }

    public function setDashboard(string $dashboard): self
    {
        $this->dashboard = $dashboard;

        return $this;
    }

    /**
     * @return string
     */
    public function getChartKey(): string {
        return $this->chartKey;
    }

    /**
     * @param string $chartKey
     * @return self
     */
    public function setChartKey(string $chartKey): self {
        $this->chartKey = $chartKey;
        return $this;
    }

    public function getData(): ?array
    {
        return $this->data;
    }

    public function setData(array $data): self
    {
        $this->data = $data;

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

}
