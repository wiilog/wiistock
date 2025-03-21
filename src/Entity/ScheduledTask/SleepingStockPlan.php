<?php

namespace App\Entity\ScheduledTask;

use App\Entity\Type;
use App\Repository\ScheduledTask\SleepingStockPlanRepository;
use App\Service\FormatService;
use DateInterval;
use DateTime;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SleepingStockPlanRepository::class)]
class SleepingStockPlan extends ScheduledTask {
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\OneToOne(inversedBy: "sleepingStockPlan", targetEntity: Type::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Type $type = null;

    /**
     * $maxStorageTime in Seconds, so the theoretic max storage time is ~= 24855 days
     */
    #[ORM\Column(type: Types::INTEGER)]
    private int $maxStorageTime = 0;

    /**
     * $maxStorageTime in Seconds, so the theoretic max storage time is ~= 24855 days
     */
    #[ORM\Column(type: Types::INTEGER)]
    private int $maxStationaryTime = 0;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $enabled = false;

    public function getId(): ?int {
        return $this->id;
    }

    public function getType(): ?Type {
        return $this->type;
    }

    public function setType(?Type $type): self {
        if($this->type && $this->type->getSleepingStockPlan() !== $this){
            $oldType = $this->type;
            $this->type = null;
            $oldType->setSleepingStockPlan(null);
        }
        $this->type = $type;
        if($this->type && $this->type->getSleepingStockPlan() !== $this){
            $this->type->setSleepingStockPlan($this);
        }

        return $this;
    }

    public function getMaxStorageTime(): int {
        return $this->maxStorageTime;
    }

    public function getMaxStorageTimeInDays(): ?int {
        $now = new DateTime();
        $dateTime = (clone $now)->modify("+$this->maxStorageTime seconds");
        return $dateTime->diff($now)->days;
    }

    public function setMaxStorageTime(int $maxStorageTime): self {
        $this->maxStorageTime = $maxStorageTime;

        return $this;
    }

    public function getMaxStationaryTime(): int {
        return $this->maxStationaryTime;
    }

    public function getMaxStationaryTimeInDays(): ?int {
        $now = new DateTime();
        $dateTime = (clone $now)->modify("+$this->maxStationaryTime seconds");
        return $dateTime->diff($now)->days;
    }

    public function setMaxStationaryTime(int $maxStationaryTime): self {
        $this->maxStationaryTime = $maxStationaryTime;

        return $this;
    }

    public function isEnabled(): bool {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): self {
        $this->enabled = $enabled;

        return $this;
    }
}
