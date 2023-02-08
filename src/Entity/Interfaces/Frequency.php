<?php

namespace App\Entity\Interfaces;

interface Frequency {
    public const ONCE = 'once-frequency';
    public const HOURLY = 'hourly-frequency';
    public const DAILY = 'every-day-frequency';
    public const WEEKLY = 'every-week-frequency';
    public const MONTHLY = 'every-month-frequency';

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

    public const LAST_DAY_OF_WEEK = 'last';
}
