<?php

namespace App\Entity\ScheduledTask;


use DateTime;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;


#[ORM\MappedSuperclass()]
abstract class ScheduledTask {

    #[ORM\OneToOne(targetEntity: ScheduleRule::class, cascade: ["persist", "remove"])]
    private ?ScheduleRule $scheduleRule = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?DateTime $lastRun = null;

    public function getScheduleRule(): ?ScheduleRule {
        return $this->scheduleRule;
    }

    public function setScheduleRule(?ScheduleRule $scheduleRule): self {
        $this->scheduleRule = $scheduleRule;

        return $this;
    }

    public function getLastRun(): ?DateTime {
        return $this->lastRun;
    }

    public function setLastRun(?DateTime $lastRun): self {
        $this->lastRun = $lastRun;
        return $this;
    }
}
