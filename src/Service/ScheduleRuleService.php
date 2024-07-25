<?php

namespace App\Service;

use App\Entity\CategorieStatut;
use App\Entity\ScheduledTask\Export;
use App\Entity\ScheduledTask\Import;
use App\Entity\ScheduledTask\InventoryMissionPlan;
use App\Entity\ScheduledTask\PurchaseRequestPlan;
use App\Entity\ScheduledTask\ScheduleRule;
use App\Entity\Statut;
use App\Exceptions\FormException;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
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
        [$hour, $minute] = explode(":", $rule->getIntervalTime());

        $begin = $rule->getBegin();

        $beginCalculation = $begin > $from
            ? (clone $begin)->setTime($hour, $minute)
            : (clone $from);

        $beginCalculationMonth = (int) $beginCalculation->format("m");
        $beginCalculationYear = (int) $beginCalculation->format("Y");

        $availableDays = Stream::from($rule->getMonthDays())
            ->filterMap(fn(mixed $day) => match ($day) {
                ScheduleRule::LAST_DAY_OF_WEEK => 32,
                "1", "15"                      => ((int) $day),
                default                        => null,
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
