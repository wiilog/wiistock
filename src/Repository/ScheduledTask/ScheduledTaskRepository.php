<?php

namespace App\Repository\ScheduledTask;

use App\Entity\ScheduledTask\ScheduledTask;

interface ScheduledTaskRepository {

    /**
     * @return array<ScheduledTask>
     */
    public function findScheduled(): array;

}
