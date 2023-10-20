<?php

namespace App\Entity;

use App\Repository\ImportScheduleRuleRepository;
use DateTime;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ImportScheduleRuleRepository::class)]
class ImportScheduleRule {

    public const ONCE = 'once-frequency';
    public const HOURLY = 'hourly-frequency';
    public const DAILY = 'every-day-frequency';
    public const WEEKLY = 'every-week-frequency';
    public const MONTHLY = 'every-month-frequency';

    public const PERIOD_TYPE_MINUTES = 'minutes';
    public const PERIOD_TYPE_HOURS = 'hours';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\OneToOne(inversedBy: "scheduleRule", targetEntity: Import::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Import $import = null;

    #[ORM\Column(type: Types::STRING, nullable: false)]
    private ?string $filePath = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: false)]
    private ?DateTime $begin = null;

    #[ORM\Column(type: Types::STRING, nullable: false)]
    private ?string $frequency = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $period = null;

    #[ORM\Column(type: Types::STRING, nullable: true)]
    private ?string $intervalTime = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $intervalPeriod = null;

    #[ORM\Column(type: Types::STRING, nullable: true)]
    private ?string $intervalType = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $weekDays = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $monthDays = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $months = null;

    public function getId(): ?int {
        return $this->id;
    }

    public function getImport(): ?Import {
        return $this->import;
    }

    public function setImport(?Import $import): self {
        if ($this->getImport() && $this->getImport() !== $import) {
            $this->getImport()->setScheduleRule(null);
        }

        $this->import = $import;

        if($import->getScheduleRule() !== $this) {
            $import->setScheduleRule($this);
        }

        return $this;
    }

    public function getBegin(): ?DateTime {
        return $this->begin;
    }

    public function setBegin(?DateTime $begin): self {
        $this->begin = $begin;
        return $this;
    }

    public function getFilePath(): ?string {
        return $this->filePath;
    }

    public function setFilePath(?string $filePath): self {
        $this->filePath = $filePath;
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
