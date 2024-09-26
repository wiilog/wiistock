<?php

namespace App\Service\WorkPeriod;

enum WorkPeriodItem: int {
    case WORKED_DAYS = 0;
    case WORK_FREE_DAYS = 1;
}
