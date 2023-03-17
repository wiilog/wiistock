<?php

namespace App\Service;

use App\Entity\ExportScheduleRule;
use App\Entity\ScheduleRule;
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

    public function calculateNextExecutionDate(ScheduleRule $rule, bool $instant = false): ?DateTime {
        $now = new DateTime();
        $now->setTime($now->format('H'), $now->format('i'), 0, 0);

        $now = new DateTime();
        $now->setTime($now->format('H'), $now->format('i'), 0, 0);

        $executionDate = match ($rule->getFrequency()) {
            ScheduleRule::ONCE => $this->calculateOnce($rule, $now),
            ScheduleRule::DAILY => $this->calculateFromDailyRule($rule, $now, $instant),
            ScheduleRule::WEEKLY => $this->calculateFromWeeklyRule($rule, $now, $instant),
            ScheduleRule::HOURLY => $this->calculateFromHourlyRule($rule, $now),
            ScheduleRule::MONTHLY => $this->calculateFromMonthlyRule($rule, $now, $instant),
            default => throw new RuntimeException('Invalid schedule rule frequency'),
        };

        if ($rule instanceof ExportScheduleRule){
            $export = $rule->getExport();
            if ($export->isForced()) {
                $now->setTime($now->format('H'), ((int)$now->format('i')) + 2, 0, 0);
                $executionDate = min($now, $executionDate);
            }
        }

        return $executionDate;
    }

    public function calculateFromWeeklyRule(ScheduleRule $rule, DateTime $now, bool $instant): ?DateTime {
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

        $weeksDifferential = floor($now->diff($rule->getBegin())->days / 7);
        $add = $weeksDifferential + $weeksDifferential % $rule->getPeriod();
        $nextOccurrence->modify("+$add weeks");

        $goToNextWeek = false;
        if ($now->format("W") != $nextOccurrence->format("W")) {
            $day = $rule->getWeekDays()[0];
        } else {
            $isTimeEqualOrBefore = $this->isTimeEqualOrBefore($rule->getIntervalTime(), $now);
            $isTimeEqual = $this->isTimeEqual($rule->getIntervalTime(), $now);
            $currentDay = $now->format("N");

            $day = Stream::from($rule->getWeekDays())
                ->filter(function($day) use ($isTimeEqual, $isTimeEqualOrBefore, $currentDay, $instant) {
                    if ($instant && $isTimeEqual && $day === $currentDay) {
                        return true;
                    } else if ($isTimeEqualOrBefore) {
                        return $day > $currentDay;
                    } else {
                        return $day >= $currentDay;
                    }
                })
                ->firstOr(function() use ($rule, &$goToNextWeek) {
                    $goToNextWeek = true;
                    return $rule->getWeekDays()[0];
                });
        }
        if ($goToNextWeek) {
            $nextOccurrence->modify("+{$rule->getPeriod()} week");
        }

        $dayAsString = $DAY_LABEL[$day];
        $nextOccurrence->modify("$dayAsString this week");
        $nextOccurrence->setTime($hour, $minute);

        return $nextOccurrence;
    }

    public function calculateFromMonthlyRule(ScheduleRule $rule, DateTime $now, bool $instant): ?DateTime {
        $start = ($now > $rule->getBegin()) ? $now : $rule->getBegin();
        $isTimeEqualOrBefore = $this->isTimeEqualOrBefore($rule->getIntervalTime(), $start);
        $isTimeEqual = $this->isTimeEqual($rule->getIntervalTime(), $start);

        $year = $start->format("Y");
        $currentMonth = $start->format("n");
        $currentDay = (int) $start->format("j");
        $currentLastDayMonth = (int) (clone $start)
            ->modify('last day this month')
            ->format("j");

        $day = Stream::from($rule->getMonthDays())
            ->filter(function ($day) use ($isTimeEqualOrBefore, $currentDay, $instant, $isTimeEqual, $currentLastDayMonth) {
                $day = intval($day === ScheduleRule::LAST_DAY_OF_WEEK ? 32 : $day);
                if ($instant && $isTimeEqual && ($day === $currentDay || ($day === 32 && $currentDay === $currentLastDayMonth))) {
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
        $ignoreCurrentMonth = !($instant && $isDayEqual && $isTimeEqual) && ($isDayBefore || ($isDayEqual && $isTimeEqualOrBefore));

        $month = Stream::from($rule->getMonths())
            ->filter(fn($month) => $ignoreCurrentMonth ? $month > $currentMonth : $month >= $currentMonth)
            ->firstOr(function() use ($rule, &$year) {
                return $rule->getMonths()[0];
            });

        return DateTime::createFromFormat("d/m/Y H:i", "$day/$month/$year {$rule->getIntervalTime()}");
    }

    public function calculateFromDailyRule(ScheduleRule $rule, DateTime $now, bool $instant): ?DateTime {
        $start = $rule->getBegin();
        // set time to 0
        $start->setTime(0, 0);
        $period = $rule->getPeriod();
        [$hour, $minute] = explode(":", $rule->getIntervalTime());

        $nextOccurrence = clone $start;
        if ($now >= $start) {
            $daysDifferential = $now->diff($start)->days;

            $add = $daysDifferential - $daysDifferential % $period;
            if ($add < $daysDifferential) {
                $add += $period;
            }
            $nextOccurrence->modify("+$add day");
            $nextOccurrence->setTime($hour, $minute);

            if ($instant && $this->isTimeEqual($rule->getIntervalTime(), $now)) {
                return $nextOccurrence;
            }

            if ($this->isTimeEqualOrBefore($rule->getIntervalTime(), $now)) {
                $nextOccurrence->modify("+1 day");
            }
        } else {
            $nextOccurrence->setTime($hour, $minute);
        }

        return $nextOccurrence;
    }

    public function calculateFromHourlyRule(ScheduleRule $rule, DateTime $now): ?DateTime {
        $intervalPeriod = $rule->getIntervalPeriod();
        if (!$intervalPeriod) {
            return null;
        }

        $hoursBetweenDates = intval($now->diff($rule->getBegin(), true)->format("%h"));
        if($hoursBetweenDates % $intervalPeriod === 0) {
            if (intval($rule->getBegin()->format('i')) < intval($now->format('i'))) {
                $hoursToAdd = $intervalPeriod;
            } else {
                $hoursToAdd = 0;
            }
        } else {
            $hoursToAdd = $hoursBetweenDates % $intervalPeriod;
        }

        $nextOccurrence = clone $now;
        $nextOccurrence->setTime((int)$now->format("H") + $hoursToAdd, $rule->getBegin()->format("i"));

        return $nextOccurrence;
    }

    public function calculateOnce(ScheduleRule $rule, DateTime $now): ?DateTime {
        return $rule->getLastRun() === null && $now <= $rule->getBegin()
            ? $rule->getBegin()
            : null;
    }

    private function isTimeEqualOrBefore(string $time, DateTime $date) {
        [$hour, $minute] = explode(":", $time);
        return $date->format('H') > $hour || ($date->format('H') == $hour && $date->format('i') >= $minute);
    }

    private function isTimeEqual(string $time, DateTime $date) {
        [$hour, $minute] = explode(":", $time);
        return $date->format('H') == $hour && $date->format('i') == $minute;
    }
}
