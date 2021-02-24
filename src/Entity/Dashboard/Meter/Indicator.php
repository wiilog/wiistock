<?php

namespace App\Entity\Dashboard\Meter;

use App\Entity\Dashboard;
use Doctrine\ORM\Mapping as ORM;
use App\Repository\Dashboard as DashboardRepository;

/**
 * @ORM\Entity(repositoryClass=DashboardRepository\IndicatorMeterRepository::class)
 * @ORM\Table(name="dashboard_meter_indicator")
 */
class Indicator
{

    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="integer")
     */
    private $count;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $subtitle;

    /**
     * @ORM\Column(type="string", nullable=true)
     */
    private $delay;

    /**
     * @ORM\Column(type="string", nullable=true)
     */
    private $firstDelayLine;

    /**
     * @var Dashboard\Component
     * @ORM\OneToOne(targetEntity=Dashboard\Component::class, inversedBy="indicatorMeter")
     */
    private $component;

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

    public function getSubtitle(): ?string
    {
        return $this->subtitle;
    }

    public function setSubtitle(?string $subtitle): self
    {
        $this->subtitle = $subtitle;

        return $this;
    }

    public function getDelay(): ?string
    {
        return $this->delay;
    }

    public function setDelay($delay): self
    {
        $this->delay = strval($delay);

        return $this;
    }

    public function getFirstDelayLine(): ?string
    {
        return $this->firstDelayLine;
    }

    public function setFirstDelayLine(?string $firstDelayLine): self
    {
        $this->firstDelayLine = $firstDelayLine;

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
