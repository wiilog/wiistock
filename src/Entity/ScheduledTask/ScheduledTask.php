<?php

namespace App\Entity\ScheduledTask;


interface ScheduledTask {

    public function getScheduleRule(): ?ScheduleRule;

    public function setScheduleRule(?ScheduleRule $scheduleRule): self;
}
