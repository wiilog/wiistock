<?php

namespace App\Entity\ScheduledTask;

use App\Entity\Type;
use App\Repository\ScheduledTask\SleepingStockPlanRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SleepingStockPlanRepository::class)]
class SleepingStockPlan extends ScheduledTask {
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\OneToOne(targetEntity: Type::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Type $type = null;

    /**
     * $maxStorageTime in Seconds, so the theoric max storage time is ~= 24855 days
     */
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $maxStorageTime = null;

    public function getId(): ?int {
        return $this->id;
    }

    public function getType(): ?Type {
        return $this->type;
    }

    public function setType(Type $type): self {
        $this->type = $type;

        return $this;
    }

    public function getMaxStorageTime(): ?int {
        return $this->maxStorageTime;
    }

    public function setMaxStorageTime(int $maxStorageTime): self {
        $this->maxStorageTime = $maxStorageTime;

        return $this;
    }
}
