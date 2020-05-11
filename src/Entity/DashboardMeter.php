<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\DashboardMeterRepository")
 */
class DashboardMeter
{

    public const DASHBOARD_PACKAGING = 'packaging';
    public const DASHBOARD_ADMIN = 'admin';
    public const DASHBOARD_DOCK = 'dock';

    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $meterKey;

    /**
     * @ORM\Column(type="integer")
     */
    private $count;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $label;

    /**
     * @ORM\Column(type="bigint", nullable=true)
     */
    private $delay;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $dashboard;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getMeterKey(): ?string
    {
        return $this->meterKey;
    }

    public function setMeterKey(string $meterKey): self
    {
        $this->meterKey = $meterKey;

        return $this;
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

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(?string $label): self
    {
        $this->label = $label;

        return $this;
    }

    public function getDelay(): ?int
    {
        return $this->delay;
    }

    public function setDelay(?int $delay): self
    {
        $this->delay = $delay;

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
}
