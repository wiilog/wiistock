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
    private $label;

    /**
     * @ORM\Column(type="bigint", nullable=true)
     */
    private $delay;

    /**
     * @var Dashboard\Component
     * @ORM\OneToOne (targetEntity=Dashboard\Component::class, inversedBy="indicatorMeter")
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
