<?php

namespace App\Entity\Dashboard\Meter;

use App\Entity\Dashboard;
use App\Repository\Dashboard as DashboardRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DashboardRepository\IndicatorMeterRepository::class)]
#[ORM\Table(name: 'dashboard_meter_indicator')]
class Indicator {

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'integer')]
    private ?int $count = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $subtitle = null;

    #[ORM\Column(type: 'bigint', nullable: true)]
    private ?int $delay;

    #[ORM\Column(type: 'json', nullable: false)]
    private array $subCounts = [];

    #[ORM\OneToOne(inversedBy: 'indicatorMeter', targetEntity: Dashboard\Component::class)]
    private ?Dashboard\Component $component = null;

    public function getId(): ?int {
        return $this->id;
    }

    public function getCount(): ?int {
        return $this->count;
    }

    public function setCount(int $count): self {
        $this->count = $count;

        return $this;
    }

    public function getSubtitle(): ?string {
        return $this->subtitle;
    }

    public function setSubtitle(?string $subtitle): self {
        $this->subtitle = $subtitle;

        return $this;
    }

    public function getDelay(): ?int {
        return $this->delay;
    }

    public function setDelay(?int $delay): self {
        $this->delay = $delay;

        return $this;
    }

    public function getSubCounts(): array {
        return $this->subCounts;
    }

    public function setSubCounts(array $subCounts): self {
        $this->subCounts = $subCounts;

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

        if($this->component) {
            $this->component->setMeter(null);
        }

        $this->component = $component;

        if($this->component) {
            $this->component->setMeter($this);
        }

        return $this;
    }

}
