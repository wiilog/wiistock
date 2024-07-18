<?php

namespace App\Service;

use App\Entity\ScheduledTask\ScheduleRule\ScheduleRule;
use DateTime;
use RuntimeException;
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

    public function calculateNextExecutionDate(ScheduleRule $rule,
                                               DateTime     $from): ?DateTime {
        $from = clone $from;
        $from->setTime($from->format('H'), $from->format('i'), 0, 0);

        return match ($rule->getFrequency()) {
            ScheduleRule::ONCE    => $this->calculateOnce($rule, $from),
            ScheduleRule::DAILY   => $this->calculateFromDailyRule($rule, $from),
            ScheduleRule::WEEKLY  => $this->calculateFromWeeklyRule($rule, $from),
            ScheduleRule::HOURLY  => $this->calculateFromHourlyRule($rule, $from),
            ScheduleRule::MONTHLY => $this->calculateFromMonthlyRule($rule, $from),
            default               => throw new RuntimeException('Invalid schedule rule frequency'),
        };
    }

    public function calculateFromWeeklyRule(ScheduleRule $rule,
                                            DateTime     $from): ?DateTime {
        $DAY_LABEL = [
            1 => "monday",
            2 => "tuesday",
            3 => "wednesday",
            4 => "thursday",
            5 => "friday",
            6 => "saturday",
            7 => "sunday",
        ];

        [$hour, $minute] = explode(":", $rule->getIntervalTime());
        $nextOccurrence = clone $rule->getBegin();

        $weeksDifferential = floor($from->diff($rule->getBegin())->days / 7);
        $add = $weeksDifferential + $weeksDifferential % $rule->getPeriod();
        $nextOccurrence->modify("+$add weeks");

        $goToNextWeek = false;
        if ($from->format("W") != $nextOccurrence->format("W")) {
            $nextOccurrence = max($from, $nextOccurrence);
            $day = $rule->getWeekDays()[0];
            if (intval($day) < intval($nextOccurrence->format('N'))
                || (intval($day) === intval($nextOccurrence->format('N')) && $this->isTimeBefore($rule->getIntervalTime(), $nextOccurrence))) {
                $nextOccurrence->modify('+1 week');
            }
        } else {
            $isTimeEqualOrBefore = $this->isTimeEqualOrBefore($rule->getIntervalTime(), $from);
            $isTimeEqual = $this->isTimeEqual($rule->getIntervalTime(), $from);
            $currentDay = $from->format("N");

            $availableDays = Stream::from($rule->getWeekDays())
                ->filter(function($day) use ($isTimeEqual, $isTimeEqualOrBefore, $currentDay) {
                    if ($isTimeEqual && $day === $currentDay) {
                        return true;
                    } else if ($isTimeEqualOrBefore) {
                        return $day > $currentDay;
                    } else {
                        return $day >= $currentDay;
                    }
                });

            $goToNextWeek = $availableDays->isEmpty();

            $day = $availableDays->firstOr(fn() => $rule->getWeekDays()[0] ?? null);
        }

        if ($goToNextWeek) {
            $nextOccurrence->modify("+{$rule->getPeriod()} week");
        }

        $dayAsString = $DAY_LABEL[$day];
        $nextOccurrence->modify("$dayAsString this week");
        $nextOccurrence->setTime($hour, $minute);

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
        $start = $rule->getBegin();
        // set time to 0
        $start->setTime(0, 0);
        $period = $rule->getPeriod();
        [$hour, $minute] = explode(":", $rule->getIntervalTime());

        $nextOccurrence = clone $start;
        if ($from >= $start) {
            $daysDifferential = $from->diff($start)->days;

            $add = $daysDifferential - $daysDifferential % $period;
            if ($add < $daysDifferential) {
                $add += $period;
            }
            $nextOccurrence->modify("+$add day");
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

        $hoursBetweenDates = intval($from->diff($rule->getBegin(), true)->format("%h"));
        if($hoursBetweenDates % $intervalPeriod === 0) {
            if (intval($rule->getBegin()->format('i')) <= intval($from->format('i'))) {
                $hoursToAdd = $intervalPeriod;
            } else {
                $hoursToAdd = 0;
            }
        } else {
            $hoursToAdd = $hoursBetweenDates % $intervalPeriod;
        }

        $nextOccurrence = clone $from;
        $nextOccurrence->setTime((int)$from->format("H") + $hoursToAdd, $rule->getBegin()->format("i"));

        return $nextOccurrence;
    }

    public function calculateOnce(ScheduleRule $rule,
                                  DateTime     $from): ?DateTime {
        return $rule->getLastRun() === null && $from <= $rule->getBegin()
            ? $rule->getBegin()
            : null;
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
