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
     * @var string
     * @ORM\Column(type="string", length=255)
     */
    private $axisKey;

    /**
     * @var string
     * @ORM\Column(type="string", length=255)
     */
    private $axisSubKey;

    /**
     * @var int
     * @ORM\Column(type="integer")
     */
    private $count;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $dashboard;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCount(): ?int
    {
        return $this->count;
    }

    public function setCount(int $count): self
    {
        $this->count = $count;

        return $this;
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

    /**
     * @return string
     */
    public function getAxisKey(): string {
        return $this->axisKey;
    }

    /**
     * @param string $axisKey
     * @return self
     */
    public function setAxisKey(string $axisKey): self {
        $this->axisKey = $axisKey;
        return $this;
    }

    /**
     * @return string
     */
    public function getAxisSubKey(): string
    {
        return $this->axisSubKey;
    }

    /**
     * @param string $axisSubKey
     * @return DashboardChartMeter
     */
    public function setAxisSubKey(string $axisSubKey): self
    {
        $this->axisSubKey = $axisSubKey;
        return $this;
    }


}
