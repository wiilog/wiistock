<?php

namespace App\Entity\Interfaces;

interface ScheduleRule {
    public const ONCE = 'once-frequency';
    public const HOURLY = 'hourly-frequency';
    public const DAILY = 'every-day-frequency';
    public const WEEKLY = 'every-week-frequency';
    public const MONTHLY = 'every-month-frequency';

    public const WEEKS = "weeks";
    public const MONTHS = "months";

    public const LAST_DAY_OF_WEEK = 'last';
}
