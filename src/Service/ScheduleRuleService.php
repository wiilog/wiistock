<?php

namespace App\Service;

use App\Entity\ScheduledTask\ScheduleRule\ScheduleRule;
use App\Exceptions\FormException;
use DateTime;
use RuntimeException;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Contracts\Service\Attribute\Required;
use Twig\Environment;
use WiiCommon\Helper\Stream;

class ScheduleRuleService
{
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

        [$hour, $minute] = explode(":", $rule->getIntervalTime());
        $begin = (clone $rule->getBegin())->setTime($hour, $minute);

        if ($from > $begin) {
            $sameWeek = (
                $from->format("W") === $begin->format("W")
                && $from->format("Y") === $begin->format("Y")
            );

            if ($sameWeek) {
                $availableDayInThisWeek = Stream::from($availableWeekDays)
                    ->filter(static fn(string $day, int $dayIndex) => $dayIndex > $from->format("N"));

                if (!$availableDayInThisWeek->isEmpty()) {
                    $nextExecutionDay = $availableDayInThisWeek->first();
                    $nextOccurrence = clone $from;
                    $nextOccurrence
                        ->modify("{$nextExecutionDay} this week")
                        ->setTime($hour, $minute);
                }
            }

            // if next occurence can't be on the same week of from
            if (!isset($nextOccurrence)) {
                $period = $rule->getPeriod();
                $secondsInWeek = 60 * 60 * 24 * 7;
                $secondsBetween = ($from->getTimestamp() - $begin->getTimestamp());
                $weeksBetween = floor($secondsBetween / $secondsInWeek);
                $periodBetweenCount = floor($weeksBetween / $period);
                $remainSecondsBetweenDate = ($secondsBetween % $secondsInWeek) + (($weeksBetween % $period) * $secondsInWeek);

                $add = (
                    $periodBetweenCount * $period
                    + ($remainSecondsBetweenDate > 0 ? $period : 0)
                );

                $firstAvailableDayInAWeek = Stream::from($availableWeekDays)->first();

                $nextOccurrence = (clone $begin)
                    ->modify("+$add weeks")
                    ->modify("{$firstAvailableDayInAWeek} this week")
                    ->setTime($hour, $minute);
            }
        }
        else {
            $nextOccurrence = clone $begin;
        }

        return $nextOccurrence;
    }

    public function calculateFromMonthlyRule(ScheduleRule $rule,
                                             DateTime     $from): ?DateTime {
        $start = ($from > $rule->getBegin()) ? $from : $rule->getBegin();
        $isTimeEqualOrBefore = $this->isTimeEqualOrBefore($rule->getIntervalTime(), $start);
        $isTimeEqual = $this->isTimeEqual($rule->getIntervalTime(), $start);

        $year = $start->format("Y");
        $currentMonth = $start->format("n");
        $currentDay = (int) $start->format("j");
        $currentLastDayMonth = (int) (clone $start)
            ->modify('last day this month')
            ->format("j");

        $day = Stream::from($rule->getMonthDays())
            ->filter(function ($day) use ($isTimeEqualOrBefore, $currentDay, $isTimeEqual, $currentLastDayMonth) {
                $day = intval($day === ScheduleRule::LAST_DAY_OF_WEEK ? 32 : $day);
                if ($isTimeEqual
                    && ($day === $currentDay || ($day === 32 && $currentDay === $currentLastDayMonth))) {
                    return true;
                } else if ($isTimeEqualOrBefore) {
                    return $day > $currentDay;
                } else {
                    return $day >= $currentDay;
                }
            })
            ->firstOr(fn() => $rule->getMonthDays()[0]);
        $day = $day !== ScheduleRule::LAST_DAY_OF_WEEK ? $day : $currentLastDayMonth;
        $isDayEqual = $day == $currentDay;
        $isDayBefore = $day < $currentDay;
        $ignoreCurrentMonth = (
            !($isDayEqual && $isTimeEqual)
            && ($isDayBefore || ($isDayEqual && $isTimeEqualOrBefore))
        );

        $month = Stream::from($rule->getMonths())
            ->filter(fn($month) => $ignoreCurrentMonth ? ($month > $currentMonth) : ($month >= $currentMonth))
            ->firstOr(fn() => $rule->getMonths()[0]);

        if($month < $currentMonth || $month === $currentMonth && $day < $currentDay){
            $year++;
        }
        return DateTime::createFromFormat("d/m/Y H:i", "$day/$month/$year {$rule->getIntervalTime()}");
    }

    public function calculateFromDailyRule(ScheduleRule $rule,
                                           DateTime     $from): ?DateTime {
        $period = $rule->getPeriod();
        [$hour, $minute] = explode(":", $rule->getIntervalTime());

        $start = clone $rule->getBegin();
        $start->setTime($hour, $minute);
        $nextOccurrence = clone $start;

        if ($from > $start) {
            $secondsInDay = 60 * 60 * 24;
            $secondsBetween = ($from->getTimestamp() - $start->getTimestamp());
            $daysBetween = floor($secondsBetween / $secondsInDay);
            $periodBetweenCount = floor($daysBetween / $period);
            $remainSecondsBetweenDate = ($secondsBetween % $secondsInDay) + (($daysBetween % $period) * $secondsInDay);

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
            $secondInHour = 60 * 60;

            $secondsBetweenDates = $from->getTimestamp() - $begin->getTimestamp();

            $hoursBetweenDates = floor($secondsBetweenDates / $secondInHour);

            $intervalPeriodBetweenCount = floor($hoursBetweenDates / $intervalPeriod);
            $intervalPeriodBetween = ($intervalPeriodBetweenCount * $intervalPeriod);
            $remainSecondsBetweenDate = ($hoursBetweenDates - $intervalPeriodBetween) + ($secondsBetweenDates % $secondInHour);

            $hoursToAdd = (
                $intervalPeriodBetween
                + ($remainSecondsBetweenDate > 0 ? $intervalPeriod : 0)
            );

            $nextOccurrence->modify("+{$hoursToAdd} hours");
        }

        return $nextOccurrence;
    }

    public function calculateOnce(ScheduleRule $rule,
                                  DateTime     $from): ?DateTime {
        return $rule->getLastRun() === null && $from <= $rule->getBegin()
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

    private function isTimeEqualOrBefore(string $time, DateTime $date): bool {
        [$hour, $minute] = explode(":", $time);
        return (
            $date->format('H') > $hour
            || (
                $date->format('H') == $hour
                && $date->format('i') >= $minute
            )
        );
    }

    private function isTimeBefore(string $time, DateTime $date) {
        [$hour, $minute] = explode(":", $time);
        return $date->format('H') > $hour || ($date->format('H') == $hour && $date->format('i') > $minute);
    }

    private function isTimeEqual(string $time, DateTime $date) {
        [$hour, $minute] = explode(":", $time);
        return $date->format('H') == $hour && $date->format('i') == $minute;
    }
}
