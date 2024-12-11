<?php

namespace App\Service;

use App\Entity\ScheduledTask\ScheduleRule;
use App\Exceptions\FormException;
use DateTime;
use RuntimeException;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Contracts\Service\Attribute\Required;
use Twig\Environment;
use WiiCommon\Helper\Stream;

class ScheduleRuleService
{
    private const SECONDS_IN_ONE_HOUR = 60 * 60;
    private const SECONDS_IN_A_DAY = self::SECONDS_IN_ONE_HOUR * 24;
    private const SECONDS_IN_A_WEEK = self::SECONDS_IN_A_DAY * 7;

    #[Required]
    public CacheService $cacheService;

    #[Required]
    public RefArticleDataService $refArticleDataService;

    #[Required]
    public ArticleDataService $articleDataService;

    #[Required]
    public FreeFieldService $freeFieldService;

    #[Required]
    public MailerService $mailerService;

    #[Required]
    public Environment $templating;

    #[Required]
    public FormatService $formatService;

    public function calculateNextExecution(ScheduleRule $rule,
                                           DateTime     $from): ?DateTime {

        if ($from->format('s') <= 10) {
            // ignore delta of 10seconds to calcultate nextExecution dima
            $from = (clone $from)
                ->setTime($from->format('H'), $from->format('i'));
        }

        return match ($rule->getFrequency()) {
            ScheduleRule::ONCE    => $this->calculateOnce($rule, $from),
            ScheduleRule::HOURLY  => $this->calculateFromHourlyRule($rule, $from),
            ScheduleRule::DAILY   => $this->calculateFromDailyRule($rule, $from),
            ScheduleRule::WEEKLY  => $this->calculateFromWeeklyRule($rule, $from),
            ScheduleRule::MONTHLY => $this->calculateFromMonthlyRule($rule, $from),
            default               => throw new RuntimeException('Invalid schedule rule frequency'),
        };
    }

    public function calculateFromWeeklyRule(ScheduleRule $rule,
                                            DateTime     $from): ?DateTime {
        $availableWeekDays = Stream::from([
            1 => "monday",
            2 => "tuesday",
            3 => "wednesday",
            4 => "thursday",
            5 => "friday",
            6 => "saturday",
            7 => "sunday",
        ])
            ->filter(static fn(string $day, string $dayIndex) => in_array($dayIndex, $rule->getWeekDays()))
            ->toArray();

        $hourMinute = explode(":", $rule->getIntervalTime());
        $nextOccurrenceHour = (int) $hourMinute[0];
        $nextOccurrenceMinute = (int) $hourMinute[1];
        $nextOccurrenceDaySecond = ($nextOccurrenceHour * 60 * 60 + $nextOccurrenceMinute * 60);


        $begin = (clone $rule->getBegin())->setTime($nextOccurrenceHour, $nextOccurrenceMinute);

        $fromHour = (int) $from->format("H");
        $fromMinute = (int) $from->format("i");
        $fromSecond = (int) $from->format("s");
        $fromDaySecond = ($fromHour * 60 * 60 + $fromMinute * 60 + $fromSecond);

        if ($from > $begin) {
            $availableDaysInThisWeek = Stream::from($availableWeekDays)
                ->filter(static fn(string $day, int $dayIndex) => (
                    (
                        $dayIndex == $from->format("N")
                        && ($nextOccurrenceDaySecond >= $fromDaySecond)
                    )
                    || ($dayIndex > $from->format("N"))
                ));

            $mondayBegin = (clone $begin)->modify("monday this week");

            $period = $rule->getPeriod();
            $secondsBetween = ($from->getTimestamp() - $mondayBegin->getTimestamp());
            $weeksBetween = floor($secondsBetween / self::SECONDS_IN_A_WEEK);
            $periodBetweenCount = floor($weeksBetween / $period);

            $add = ($periodBetweenCount * $period);

            $firstAvailableDayInAWeek = Stream::from($availableWeekDays)->first();

            $nextOccurrence = (clone $mondayBegin)->modify("+$add weeks");

            $sameWeek = (
                $from->format("W") === $nextOccurrence->format("W")
                && $from->format("Y") === $nextOccurrence->format("Y")
            );

            if ($sameWeek) {
                $nextExecutionDay = $availableDaysInThisWeek->first();
            }

            // execution can't be this week
            if (!isset($nextExecutionDay)) {
                $nextExecutionDay = $firstAvailableDayInAWeek;
                $nextOccurrence->modify("+{$period} weeks");
            }

            $nextOccurrence
                ->modify("{$nextExecutionDay} this week")
                ->setTime($nextOccurrenceHour, $nextOccurrenceMinute);
        }
        else {
            $nextOccurrence = clone $begin;
        }

        return $nextOccurrence;
    }

    public function calculateFromMonthlyRule(ScheduleRule $rule,
                                             DateTime     $from): ?DateTime {
        [$hour, $minute] = explode(":", $rule->getIntervalTime());

        $begin = $rule->getBegin();

        $beginCalculation = $begin > $from
            ? (clone $begin)->setTime($hour, $minute)
            : (clone $from);

        $beginCalculationMonth = (int) $beginCalculation->format("m");
        $beginCalculationYear = (int) $beginCalculation->format("Y");

        $availableDays = Stream::from($rule->getMonthDays())
            ->filterMap(fn(mixed $day) => match ($day) {
                ScheduleRule::LAST_DAY_OF_MONTH            => 32,
                (string) ScheduleRule::FIRST_DAY_OF_MONTH,
                (string) ScheduleRule::MIDDLE_DAY_OF_MONTH => ((int) $day),
                default                                    => null,
            })
            ->sort(fn(int $day1, int $day2) => ($day1 <=> $day2));

        $availableMonths = Stream::from($rule->getMonths())
            ->sort(static fn(int $month1, int $month2) => $month1 <=> $month2);

        $availableMonthsThisYear = Stream::from($availableMonths)
            ->filter(static fn(int $month) => $month >= $beginCalculationMonth)
            ->values();

        $generateOccurrence = function (int $year, int $month, int $day, int $hour, int $minute): DateTime {
            $initDay = $day > 31 ? 1 : $day;

            $year = str_pad($year, 4, "0", STR_PAD_LEFT);
            $month = str_pad($month, 2, "0", STR_PAD_LEFT);
            $initDay = str_pad($initDay, 2, "0", STR_PAD_LEFT);
            $hour = str_pad($hour, 2, "0", STR_PAD_LEFT);
            $minute = str_pad($minute, 2, "0", STR_PAD_LEFT);

            $calculatedOccurrence = DateTime::createFromFormat("Y-m-d H:i:s", "{$year}-{$month}-{$initDay} {$hour}:{$minute}:00");
            if ($day > 31) {
                $calculatedOccurrence = $calculatedOccurrence->modify("last day of this month");
            }
            return $calculatedOccurrence;
        };

        $firstTryMonth = $availableMonthsThisYear[0] ?? null;
        $fallbackMonth = $availableMonthsThisYear[1] ?? null;
        $fallbackYear = $beginCalculationYear;

        if (!isset($availableMonthsThisYear) || !isset($fallbackMonth)) {
            $fallbackMonth = $availableMonths->first();
            $fallbackYear++;
        }

        if (isset($firstTryMonth)) {
            foreach ($availableDays as $availableDay) {
                $calculatedOccurrence = $generateOccurrence($beginCalculationYear, $firstTryMonth, $availableDay, $hour, $minute);
                if ($calculatedOccurrence >= $beginCalculation) {
                    $nextOccurrence = $calculatedOccurrence;
                    break;
                }
            }
        }

        if (!isset($nextOccurrence)) {
            $fallbackDay = $availableDays->first();
            $nextOccurrence = $generateOccurrence($fallbackYear, $fallbackMonth, $fallbackDay, $hour, $minute);
        }

        return $nextOccurrence ?? null;
    }

    public function calculateFromDailyRule(ScheduleRule $rule,
                                           DateTime     $from): ?DateTime {
        $period = $rule->getPeriod();
        [$hour, $minute] = explode(":", $rule->getIntervalTime());

        $start = clone $rule->getBegin();
        $start->setTime($hour, $minute);
        $nextOccurrence = clone $start;

        if ($from > $start) {
            $fromTimestamp = $from->getTimestamp();
            $startTimeStamp = $start->getTimestamp();

            // summer / winter hour change adjusting
            if ($from->getOffset() !== $start->getOffset()) {
                $diffOffsetSeconds = $from->getOffset() - $start->getOffset();
                $startTimeStamp -= $diffOffsetSeconds;
            }

            $secondsBetween = ($fromTimestamp - $startTimeStamp);
            $daysBetween = floor($secondsBetween / self::SECONDS_IN_A_DAY);
            $periodBetweenCount = floor($daysBetween / $period);
            $remainSecondsBetweenDate = ($secondsBetween % self::SECONDS_IN_A_DAY) + (($daysBetween % $period) * self::SECONDS_IN_A_DAY);

            $add = (
                $periodBetweenCount * $period
                + ($remainSecondsBetweenDate > 0 ? $period : 0)
            );
            $nextOccurrence->modify("+$add days");
        }

        $nextOccurrence->setTime($hour, $minute);

        return $nextOccurrence;
    }

    public function calculateFromHourlyRule(ScheduleRule $rule,
                                            DateTime     $from): ?DateTime {
        $intervalPeriod = $rule->getIntervalPeriod();
        if (!$intervalPeriod) {
            return null;
        }

        $begin = $rule->getBegin();

        $nextOccurrence = clone $begin;
        if ($from > $begin) {
            $secondsBetweenDates = $from->getTimestamp() - $begin->getTimestamp();

            $hoursBetweenDates = floor($secondsBetweenDates / self::SECONDS_IN_ONE_HOUR);

            $intervalPeriodBetweenCount = floor($hoursBetweenDates / $intervalPeriod);
            $intervalPeriodBetween = ($intervalPeriodBetweenCount * $intervalPeriod);
            $remainSecondsBetweenDate = ($hoursBetweenDates - $intervalPeriodBetween) + ($secondsBetweenDates % self::SECONDS_IN_ONE_HOUR);

            $hoursToAdd = (
                $intervalPeriodBetween
                + ($remainSecondsBetweenDate > 0 ? $intervalPeriod : 0)
            );

            // summer / winter hour change adjusting
            if ($from->getOffset() !== $begin->getOffset()) {
                $diffOffsetSeconds = $from->getOffset() - $begin->getOffset();
                $diffOffsetHour = floor($diffOffsetSeconds / self::SECONDS_IN_ONE_HOUR);
                if ($hoursBetweenDates + $diffOffsetHour >= 0) {
                    $hoursToAdd += $diffOffsetHour;
                }
            }

            $nextOccurrence->modify("+{$hoursToAdd} hours");
        }

        return $nextOccurrence;
    }

    public function calculateOnce(ScheduleRule $rule,
                                  DateTime     $from): ?DateTime {
        return $from <= $rule->getBegin()
            ? $rule->getBegin()
            : null;
    }

    public function updateRule(?ScheduleRule $scheduleRule,
                               ParameterBag  $data): ScheduleRule {

        $startDate = $data->get("startDate");
        if(!$startDate){
            throw new FormException("Veuillez choisir une fréquence pour votre export planifié.");
        }

        $begin = $this->formatService->parseDatetime($startDate);

        if (in_array($data->get("frequency"), [ScheduleRule::DAILY, ScheduleRule::WEEKLY, ScheduleRule::MONTHLY])) {
            $begin->setTime(0, 0);
        }

        return ($scheduleRule ?: new ScheduleRule())
            ->setBegin($begin)
            ->setFrequency($data->get("frequency"))
            ->setPeriod($data->get("repeatPeriod"))
            ->setIntervalPeriod($data->get("intervalPeriod"))
            ->setIntervalTime($data->get("intervalTime"))
            ->setMonths($data->get("months") ? explode(",", $data->get("months")) : null)
            ->setMonthDays($data->get("monthDays") ? explode(",", $data->get("monthDays")) : null)
            ->setWeekDays($data->get("weekDays") ? explode(",", $data->get("weekDays")) : null);
    }
}
