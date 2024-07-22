<?php

namespace App\Tests\Service;

use App\Entity\ScheduledTask\ScheduleRule\ScheduleRule;
use App\Service\ScheduleRuleService;
use DateTime;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\ParameterBag;

class ScheduleRuleServiceTest extends KernelTestCase {

    private const ONCE_SCHEDULE_RULES_TO_TEST = [
        [
            "scheduleRule" => [
                "frequency" => ScheduleRule::ONCE,
                "startDate" => "2024-01-01 17:00:00",
            ],
            "from" => "2024-01-01 17:00:00",
            "expected" => "2024-01-01 17:00:00",
        ],
        [
            "scheduleRule" => [
                "frequency" => ScheduleRule::ONCE,
                "startDate" => "2024-01-02 18:00:00",
            ],
            "from" => "2024-01-01 17:00:00",
            "expected" => "2024-01-02 18:00:00",
        ],
        [
            "scheduleRule" => [
                "frequency" => ScheduleRule::ONCE,
                "startDate" => "2024-01-03 12:00:00",
            ],
            "from" => "2024-01-03 17:00:00",
            "expected" => null,
        ],
    ];
    private const HOURLY_SCHEDULE_RULES_TO_TEST = [
        [ // begin = from
            "scheduleRule" => [
                "frequency" => ScheduleRule::HOURLY,
                "startDate" => "2024-01-01 17:00:00",
                "intervalPeriod" => 2,
            ],
            "from" => "2024-01-01 17:00:00",
            "expected" => "2024-01-01 17:00:00",
        ],
        [ // begin < form (seconds)
            "scheduleRule" => [
                "frequency" => ScheduleRule::HOURLY,
                "startDate" => "2024-01-01 17:00:00",
                "intervalPeriod" => 2,
            ],
            "from" => "2024-01-01 17:00:12",
            "expected" => "2024-01-01 19:00:00",
        ],
        [ // begin < from (hour)
            "scheduleRule" => [
                "frequency" => ScheduleRule::HOURLY,
                "startDate" => "2024-01-01 17:00:00",
                "intervalPeriod" => 2,
            ],
            "from" => "2024-01-01 18:00:00",
            "expected" => "2024-01-01 19:00:00",
        ],
        [ // begin < from (day)
            "scheduleRule" => [
                "frequency" => ScheduleRule::HOURLY,
                "startDate" => "2024-01-01 17:00:00",
                "intervalPeriod" => 2,
            ],
            "from" => "2024-01-02 08:00:00",
            "expected" => "2024-01-02 09:00:00",
        ],
        [ // begin < from (day)
            "scheduleRule" => [
                "frequency" => ScheduleRule::HOURLY,
                "startDate" => "2024-01-01 17:00:00",
                "intervalPeriod" => 2,
            ],
            "from" => "2024-01-02 11:00:00",
            "expected" => "2024-01-02 11:00:00",
        ],
        [ // begin > from (minutes)
            "scheduleRule" => [
                "frequency" => ScheduleRule::HOURLY,
                "startDate" => "2024-01-01 17:15:00",
                "intervalPeriod" => 2,
            ],
            "from" => "2024-01-01 17:10:00",
            "expected" => "2024-01-01 17:15:00",
        ],
        [ // begin > from (hour)
            "scheduleRule" => [
                "frequency" => ScheduleRule::HOURLY,
                "startDate" => "2024-01-01 17:15:00",
                "intervalPeriod" => 2,
            ],
            "from" => "2024-01-01 16:20:00",
            "expected" => "2024-01-01 17:15:00",
        ],
        [ // begin > from (day)
            "scheduleRule" => [
                "frequency" => ScheduleRule::HOURLY,
                "startDate" => "2024-01-03 17:15:00",
                "intervalPeriod" => 2,
            ],
            "from" => "2024-01-01 19:15:00",
            "expected" => "2024-01-03 17:15:00",
        ],
    ];
    private const DAILY_SCHEDULE_RULES_TO_TEST = [
        [ // on startDate
            "scheduleRule" => [
                "frequency" => ScheduleRule::DAILY,
                "startDate" => "2024-01-01",
                "repeatPeriod" => 3,
                "intervalTime" => '17:00',
            ],
            "from" => "2024-01-01 17:00:00",
            "expected" => "2024-01-01 17:00:00",
        ],
        [ //
            "scheduleRule" => [
                "frequency" => ScheduleRule::DAILY,
                "startDate" => "2024-01-01",
                "repeatPeriod" => 3,
                "intervalTime" => '18:00',
            ],
            "from" => "2024-01-01 17:00:00",
            "expected" => "2024-01-01 18:00:00",
        ],
        [ //
            "scheduleRule" => [
                "frequency" => ScheduleRule::DAILY,
                "startDate" => "2024-01-01",
                "repeatPeriod" => 3,
                "intervalTime" => '18:00',
            ],
            "from" => "2024-01-07 19:00:00",
            "expected" => "2024-01-10 18:00:00",
        ],
        [ //
            "scheduleRule" => [
                "frequency" => ScheduleRule::DAILY,
                "startDate" => "2024-01-01",
                "repeatPeriod" => 3,
                "intervalTime" => '19:00',
            ],
            "from" => "2024-01-07 19:00:00",
            "expected" => "2024-01-07 19:00:00",
        ],
        [ //
            "scheduleRule" => [
                "frequency" => ScheduleRule::DAILY,
                "startDate" => "2024-01-05",
                "repeatPeriod" => 3,
                "intervalTime" => '18:00',
            ],
            "from" => "2024-01-01 19:00:00",
            "expected" => "2024-01-05 18:00:00",
        ],
    ];

    private const WEEKLY_SCHEDULE_RULES_TO_TEST = [
        [
            "scheduleRule" => [
                "frequency" => ScheduleRule::WEEKLY,
                "startDate" => "2024-07-22",
                "repeatPeriod" => 2,
                "intervalTime" => '17:00',
                "weekDays" => '1,3', // 1 (monday) to 7 (sunday)
            ],
            "from" => "2024-07-19 17:00:00",
            "expected" => "2024-07-22 17:00:00", // monday
        ],
        [
            "scheduleRule" => [
                "frequency" => ScheduleRule::WEEKLY,
                "startDate" => "2024-07-22",
                "repeatPeriod" => 2,
                "intervalTime" => '17:00',
                "weekDays" => '1,3', // 1 (monday) to 7 (sunday)
            ],
            "from" => "2024-07-22 18:00:00",
            "expected" => "2024-07-24 17:00:00", // wednesday
        ],
        [
            "scheduleRule" => [
                "frequency" => ScheduleRule::WEEKLY,
                "startDate" => "2024-07-22",
                "repeatPeriod" => 2,
                "intervalTime" => '17:00',
                "weekDays" => '1,3', // 1 (monday) to 7 (sunday)
            ],
            "from" => "2024-07-22 17:00:22",
            "expected" => "2024-07-24 17:00:00", // wednesday
        ],
        [
            "scheduleRule" => [
                "frequency" => ScheduleRule::WEEKLY,
                "startDate" => "2024-07-22",
                "repeatPeriod" => 2,
                "intervalTime" => '17:00',
                "weekDays" => '1,3', // 1 (monday) to 7 (sunday)
            ],
            "from" => "2024-07-22 17:00:00",
            "expected" => "2024-07-22 17:00:00", // monday
        ],
        [
            "scheduleRule" => [
                "frequency" => ScheduleRule::WEEKLY,
                "startDate" => "2024-07-22",
                "repeatPeriod" => 2,
                "intervalTime" => '17:00',
                "weekDays" => '1,3', // 1 (monday) to 7 (sunday)
            ],
            "from" => "2024-07-23 17:00:00", // thursday
            "expected" => "2024-07-24 17:00:00", // wednesday this week
        ],
        [
            "scheduleRule" => [
                "frequency" => ScheduleRule::WEEKLY,
                "startDate" => "2024-07-22",
                "repeatPeriod" => 2,
                "intervalTime" => '17:00',
                "weekDays" => '1,3', // 1 (monday) to 7 (sunday)
            ],
            "from" => "2024-07-25 17:00:00", // thursday same week than beginning
            "expected" => "2024-08-05 17:00:00", // monday the next execution week
        ],
        [
            "scheduleRule" => [
                "frequency" => ScheduleRule::WEEKLY,
                "startDate" => "2024-07-22",
                "repeatPeriod" => 2,
                "intervalTime" => '17:00',
                "weekDays" => '1,3', // 1 (monday) to 7 (sunday)
            ],
            "from" => "2024-07-29 16:00:00", // monday the week next the beginning
            "expected" => "2024-08-05 17:00:00", // monday the next execution week
        ],
    ];

    /** @var ScheduleRuleService  */
    private ScheduleRuleService $scheduleRuleService;


    public function setUp(): void {
        self::bootKernel();
        $container = static::getContainer();

        $this->scheduleRuleService = $container->get(ScheduleRuleService::class);
    }

    public function testCalculateNextExecutionOnce(): void {
        $this->testCalculateNextExecutionOnScheduleRules(self::ONCE_SCHEDULE_RULES_TO_TEST);
    }

    public function testCalculateNextExecutionHourly(): void {
        $this->testCalculateNextExecutionOnScheduleRules(self::HOURLY_SCHEDULE_RULES_TO_TEST);
    }

    public function testCalculateNextExecutionDaily(): void {
        $this->testCalculateNextExecutionOnScheduleRules(self::DAILY_SCHEDULE_RULES_TO_TEST);
    }

    public function testCalculateNextExecutionWeekly(): void {
        $this->testCalculateNextExecutionOnScheduleRules(self::WEEKLY_SCHEDULE_RULES_TO_TEST);
    }

    private function testCalculateNextExecutionOnScheduleRules(array $scheduleRules): void {
        foreach ($scheduleRules as $test) {
            $from = new DateTime($test["from"]);
            $expected = isset($test["expected"]) ? new DateTime($test["expected"]) : null;
            $scheduleRule = $this->createRule($test["scheduleRule"]);

            $calculated = $this->scheduleRuleService->calculateNextExecution($scheduleRule, $from);

            $this->assertEquals($calculated, $expected);
        }
    }

    private function createRule(array $data): ScheduleRule {
        return $this->scheduleRuleService->updateRule(null, new ParameterBag($data));
    }

}
