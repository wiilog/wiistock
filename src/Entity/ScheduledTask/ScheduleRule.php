<?php

namespace App\Entity\ScheduledTask;

use App\Repository\ScheduledTask\ScheduleRuleRepository;
use DateTime;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ScheduleRuleRepository::class)]
class ScheduleRule {
    public const ONCE = 'once-frequency';
    public const HOURLY = 'hourly-frequency';
    public const DAILY = 'every-day-frequency';
    public const WEEKLY = 'every-week-frequency';
    public const MONTHLY = 'every-month-frequency';

    public const FIRST_DAY_OF_MONTH = 1;
    public const MIDDLE_DAY_OF_MONTH = 15;
    public const LAST_DAY_OF_MONTH = 'last';

    public const MONTHLY_AVAILABLE_DAYS = [
        self::FIRST_DAY_OF_MONTH,
        self::MIDDLE_DAY_OF_MONTH,
        self::LAST_DAY_OF_MONTH
    ];

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

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?DateTime $begin = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    //For the "daily" and "weekly" scheduled imports
    private ?string $frequency = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    //For the "daily" and "weekly" scheduled imports
    private ?int $period = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    //For the "hourly" frequency when the hours or minutes were chosen
    private ?string $intervalTime = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    //For the "hourly" frequency when the hours or minutes were chosen
    private ?int $intervalPeriod = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    //Only for the "weekly" scheduled import
    private ?array $weekDays = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    //Only for the "month" scheduled import
    private ?array $monthDays = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    //Only for the "month" scheduled import
    private ?array $months = null;

    public function getId(): ?int {
        return $this->id;
    }

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

    public function clone(): self {
        return (new static())
            ->setFrequency($this->getFrequency())
            ->setPeriod($this->getPeriod())
            ->setIntervalTime($this->getIntervalTime())
            ->setIntervalPeriod($this->getIntervalPeriod())
            ->setBegin($this->getBegin())
            ->setMonths($this->getMonths())
            ->setMonthDays($this->getMonthDays())
            ->setWeekDays($this->getWeekDays());
    }
}
