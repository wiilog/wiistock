<?php

namespace App\Entity;

use DateTime;
use Doctrine\ORM\Mapping as ORM;

#[ORM\MappedSuperclass()]
abstract class ScheduleRule {
    public const ONCE = 'once-frequency';
    public const HOURLY = 'hourly-frequency';
    public const DAILY = 'every-day-frequency';
    public const WEEKLY = 'every-week-frequency';
    public const MONTHLY = 'every-month-frequency';
    public const LAST_DAY_OF_WEEK = 'last';

    public const FREQUENCIES = [
        self::ONCE,
        self::HOURLY,
        self::DAILY,
        self::WEEKLY,
        self::MONTHLY,
    ];

    public const FREQUENCIES_LABELS = [
        self::ONCE => 'Une seule fois',
        self::HOURLY => 'Toutes les heures',
        self::DAILY => 'Tous les jours',
        self::WEEKLY => 'Toutes les semaines',
        self::MONTHLY => 'Tous les mois',
    ];

    public const PERIOD_TYPE_MINUTES = 'minutes';
    public const PERIOD_TYPE_HOURS = 'hours';

    #[ORM\Column(type: "datetime")]
    private ?DateTime $begin = null;

    #[ORM\Column(type: "string", length: 255, nullable: true)]
    //For the "daily" and "weekly" scheduled imports
    private ?string $frequency = null;

    #[ORM\Column(type: "integer", length: 255, nullable: true)]
    //For the "daily" and "weekly" scheduled imports
    private ?int $period = null;

    #[ORM\Column(type: "string", length: 255, nullable: true)]
    //For the "hourly" frequency when the hours or minutes were chosen
    private ?string $intervalTime = null;

    #[ORM\Column(type: "integer", length: 255, nullable: true)]
    //For the "hourly" frequency when the hours or minutes were chosen
    private ?int $intervalPeriod = null;

    #[ORM\Column(type: "json", length: 255, nullable: true)]
    //Only for the "weekly" scheduled import
    private ?array $weekDays = null;

    #[ORM\Column(type: "json", length: 255, nullable: true)]
    //Only for the "month" scheduled import
    private ?array $monthDays = null;

    #[ORM\Column(type: "json", length: 255, nullable: true)]
    //Only for the "month" scheduled import
    private ?array $months = null;

    public function getBegin(): ?DateTime {
        return $this->begin;
    }

    public function setBegin(?DateTime $begin): self {
        $this->begin = $begin;
        return $this;
    }

    public function getFrequency(): ?string {
        return $this->frequency;
    }

    public function setFrequency(string $frequency): self {
        $this->frequency = $frequency;
        return $this;
    }

    public function getPeriod(): ?int {
        return $this->period;
    }

    public function setPeriod(?int $period): self {
        $this->period = $period;
        return $this;
    }

    public function getIntervalTime(): ?string {
        return $this->intervalTime;
    }

    public function setIntervalTime(?string $intervalTime): self {
        $this->intervalTime = $intervalTime;
        return $this;
    }

    public function getIntervalPeriod(): ?int {
        return $this->intervalPeriod;
    }

    public function setIntervalPeriod(?int $intervalPeriod): self {
        $this->intervalPeriod = $intervalPeriod;
        return $this;
    }

    public function getWeekDays(): ?array {
        return $this->weekDays;
    }

    public function setWeekDays(?array $weekDays): self {
        $this->weekDays = $weekDays;
        return $this;
    }

    public function getMonthDays(): ?array {
        return $this->monthDays;
    }

    public function setMonthDays(?array $monthDays): self {
        $this->monthDays = $monthDays;
        return $this;
    }

    public function getMonths(): ?array {
        return $this->months;
    }

    public function setMonths(?array $months): self {
        $this->months = $months;
        return $this;
    }
}
