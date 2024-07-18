<?php

namespace App\Entity\ScheduledTask;


use App\Entity\ScheduledTask\ScheduleRule\ScheduleRule;
use DateTimeInterface;

interface ScheduledTask {

    public function getScheduleRule(): ?ScheduleRule;

    public function setScheduleRule(?ScheduleRule $scheduleRule): self;
}
