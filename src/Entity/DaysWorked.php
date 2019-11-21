<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\DaysWorkedRepository")
 */
class DaysWorked
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $day;

    /**
     * @ORM\Column(type="boolean")
     */
    private $worked;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $times;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private $displayOrder;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getWorked(): ?bool
    {
        return $this->worked;
    }

    public function setWorked(bool $worked): self
    {
        $this->worked = $worked;

        return $this;
    }

    public function getTimes(): ?string
    {
        return $this->times;
    }

    public function setTimes(?string $times): self
    {
        $this->times = $times;

        return $this;
    }

    public function getDay(): ?string
    {
        return $this->day;
    }

    public function setDay(?string $day): self
    {
        $this->day = $day;

        return $this;
    }

    public function getDisplayOrder(): ?int
    {
        return $this->displayOrder;
    }

    public function setDisplayOrder(?int $displayOrder): self
    {
        $this->displayOrder = $displayOrder;

        return $this;
    }

    public function getTimeArray(): array
    {
        $daysPeriod = explode(';', $this->getTimes());
        $afternoon = $daysPeriod[1];
        $morning = $daysPeriod[0];

        $afternoonLastHourAndMinute = explode('-', $afternoon)[1];
        $afternoonFirstHourAndMinute = explode('-', $afternoon)[0];
        $morningLastHourAndMinute = explode('-', $morning)[1];
        $morningFirstHourAndMinute = explode('-', $morning)[0];

        $afternoonLastHour = intval(explode(':', $afternoonLastHourAndMinute)[0]);
        $afternoonLastMinute = intval(explode(':', $afternoonLastHourAndMinute)[1]);
        $afternoonFirstHour = intval(explode(':', $afternoonFirstHourAndMinute)[0]);
        $afternoonFirstMinute = intval(explode(':', $afternoonFirstHourAndMinute)[1]);

        $morningLastHour = intval(explode(':', $morningLastHourAndMinute)[0]);
        $morningLastMinute = intval(explode(':', $morningLastHourAndMinute)[1]);
        $morningFirstHour = intval(explode(':', $morningFirstHourAndMinute)[0]);
        $morningFirstMinute = intval(explode(':', $morningFirstHourAndMinute)[1]);

        return [
            $morningFirstHour, $morningFirstMinute, $morningLastHour, $morningLastMinute,
            $afternoonFirstHour, $afternoonFirstMinute, $afternoonLastHour, $afternoonLastMinute
        ];
    }

    public function getTimeWorkedDuringThisDay(): int
    {
        $daysPeriod = explode(';', $this->getTimes());
        $afternoon = $daysPeriod[1];
        $morning = $daysPeriod[0];

        $afternoonLastHourAndMinute = explode('-', $afternoon)[1];
        $afternoonFirstHourAndMinute = explode('-', $afternoon)[0];
        $morningLastHourAndMinute = explode('-', $morning)[1];
        $morningFirstHourAndMinute = explode('-', $morning)[0];

        $afternoonLastHour = intval(explode(':', $afternoonLastHourAndMinute)[0]);
        $afternoonLastMinute = intval(explode(':', $afternoonLastHourAndMinute)[1]);
        $afternoonFirstHour = intval(explode(':', $afternoonFirstHourAndMinute)[0]);
        $afternoonFirstMinute = intval(explode(':', $afternoonFirstHourAndMinute)[1]);
        $afternoonMinutes = (($afternoonLastHour * 60) + $afternoonLastMinute) - (($afternoonFirstHour * 60) + $afternoonFirstMinute);

        $morningLastHour = intval(explode(':', $morningLastHourAndMinute)[0]);
        $morningLastMinute = intval(explode(':', $morningLastHourAndMinute)[1]);
        $morningFirstHour = intval(explode(':', $morningFirstHourAndMinute)[0]);
        $morningFirstMinute = intval(explode(':', $morningFirstHourAndMinute)[1]);
        $morningMinutes = (($morningLastHour * 60) + $morningLastMinute) - (($morningFirstHour * 60) + $morningFirstMinute);

        $totalMinutes = $afternoonMinutes + $morningMinutes;
        return $totalMinutes;
    }

    public function getTimeBreakThisDay(): int {
        $timeArray = $this->getTimeArray();
        return (($timeArray[4]*60) + $timeArray[5]) - (($timeArray[2]*60) + $timeArray[3]);
    }
}
