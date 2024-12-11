<?php

namespace App\Tests\Service;

use App\Entity\ScheduledTask\ScheduleRule;
use App\Service\ScheduleRuleService;
use DateTime;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\ParameterBag;

class ScheduleRuleServiceTest extends KernelTestCase {

    /** @var ScheduleRuleService  */
    private ScheduleRuleService $scheduleRuleService;

    public function setUp(): void {
        self::bootKernel();
        $container = static::getContainer();

        $this->scheduleRuleService = $container->get(ScheduleRuleService::class);
    }

    /**
     * @dataProvider onceScheduleRulesProvider
     * @dataProvider hourlyScheduleRulesProvider
     * @dataProvider dailyScheduleRulesProvider
     * @dataProvider weeklyScheduleRulesProvider
     * @dataProvider monthlyScheduleRulesProvider
     */
    public function testCalculateNextExecution(array   $scheduleRuleArray,
                                               ?string $fromStr,
                                               ?string $expectedStr): void {
        $from = new DateTime($fromStr);
        $expected = isset($expectedStr) ? new DateTime($expectedStr) : null;
        $scheduleRule = $this->createRule($scheduleRuleArray);

        $calculated = $this->scheduleRuleService->calculateNextExecution($scheduleRule, $from);

        $log = [
            "scheduleRule" => $scheduleRuleArray,
            "from" => $fromStr,
            "expected" => $expectedStr,
        ];
        $this->assertEquals($calculated, $expected, json_encode($log, JSON_PRETTY_PRINT));
    }

    private function createRule(array $data): ScheduleRule {
        return $this->scheduleRuleService->updateRule(null, new ParameterBag($data));
    }

    private function onceScheduleRulesProvider(): array {
        return [
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
        ];
    }

    private function hourlyScheduleRulesProvider(): array {
        return [
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
            [
                // winter hour change between 26/10 & 27/10
                // the 27/10 at 04:00 -> 27/10 03:00
                "scheduleRule" => [
                    "frequency" => ScheduleRule::HOURLY,
                    "startDate" => "2024-10-26 08:00:00",
                    "intervalPeriod" => 1,
                ],
                "from" => "2024-10-27 12:01:00",
                "expected" => "2024-10-27 13:00:00",
            ],
            [
                // winter hour change between 26/10 & 27/10
                // the 27/10 at 04:00 -> 27/10 03:00
                // multiple days jump
                "scheduleRule" => [
                    "frequency" => ScheduleRule::HOURLY,
                    "startDate" => "2024-10-26 08:00:00",
                    "intervalPeriod" => 1,
                ],
                "from" => "2024-11-06 12:01:00",
                "expected" => "2024-11-06 13:00:00",
            ],
            [
                // winter hour change between 26/10 & 27/10
                // the 27/10 at 04:00 -> 27/10 03:00
                // 2 hours interval
                "scheduleRule" => [
                    "frequency" => ScheduleRule::HOURLY,
                    "startDate" => "2024-10-26 08:00:00",
                    "intervalPeriod" => 2,
                ],
                "from" => "2024-10-27 12:01:00",
                "expected" => "2024-10-27 13:00:00",
            ],
            [
                // summer hour change between 30/03 & 31/03
                // the 31/03 at 02:00 -> 31/03 03:00
                // 1 hour interval
                "scheduleRule" => [
                    "frequency" => ScheduleRule::HOURLY,
                    "startDate" => "2024-03-30 08:00:00",
                    "intervalPeriod" => 1,
                ],
                "from" => "2024-03-31 12:01:00",
                "expected" => "2024-03-31 13:00:00",
            ],
            [
                // summer hour change between 30/03 & 31/03
                // the 31/03 at 02:00 -> 31/03 03:00
                // multiple days jump
                "scheduleRule" => [
                    "frequency" => ScheduleRule::HOURLY,
                    "startDate" => "2024-03-30 08:00:00",
                    "intervalPeriod" => 1,
                ],
                "from" => "2024-04-15 12:01:00",
                "expected" => "2024-04-15 13:00:00",
            ],
            [
                // summer hour change between 30/03 & 31/03
                // the 31/03 at 02:00 -> 31/03 03:00
                // 2 hour interval
                "scheduleRule" => [
                    "frequency" => ScheduleRule::HOURLY,
                    "startDate" => "2024-03-30 08:00:00",
                    "intervalPeriod" => 2,
                ],
                "from" => "2024-03-31 12:01:00",
                "expected" => "2024-03-31 13:00:00",
            ],
            [
                // summer & winter hour change
                // 1 hour interval
                "scheduleRule" => [
                    "frequency" => ScheduleRule::HOURLY,
                    "startDate" => "2024-03-30 08:00:00",
                    "intervalPeriod" => 1,
                ],
                "from" => "2024-12-20 12:01:00",
                "expected" => "2024-12-20 13:00:00",
            ],
            [
                // summer & winter hour change
                // 1 hour interval
                "scheduleRule" => [
                    "frequency" => ScheduleRule::HOURLY,
                    "startDate" => "2024-03-30 08:00:00",
                    "intervalPeriod" => 2,
                ],
                "from" => "2024-12-20 12:01:00",
                "expected" => "2024-12-20 14:00:00",
            ],
        ];
    }

    private function dailyScheduleRulesProvider(): array {
        return [
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
            [ // before hour changing
                "scheduleRule" => [
                    "frequency" => ScheduleRule::DAILY,
                    "startDate" => "2024-04-15",
                    "repeatPeriod" => 1,
                    "intervalTime" => '18:00',
                ],
                "from" => "2024-10-27 17:55:00",
                "expected" => "2024-10-27 18:00:00",
            ],
            [ // after hour changing
                "scheduleRule" => [
                    "frequency" => ScheduleRule::DAILY,
                    "startDate" => "2024-04-15",
                    "repeatPeriod" => 1,
                    "intervalTime" => '18:00',
                ],
                "from" => "2024-10-26 17:55:00",
                "expected" => "2024-10-26 18:00:00",
            ],
            [ // before the second hour changing
                "scheduleRule" => [
                    "frequency" => ScheduleRule::DAILY,
                    "startDate" => "2024-04-15",
                    "repeatPeriod" => 1,
                    "intervalTime" => '18:00',
                ],
                "from" => "2025-03-29 17:55:00",
                "expected" => "2025-03-29 18:00:00",
            ],
            [ // two hour changing
                "scheduleRule" => [
                    "frequency" => ScheduleRule::DAILY,
                    "startDate" => "2024-04-15",
                    "repeatPeriod" => 1,
                    "intervalTime" => '18:00',
                ],
                "from" => "2025-03-30 17:55:00",
                "expected" => "2025-03-30 18:00:00",
            ],
        ];
    }

    private function weeklyScheduleRulesProvider(): array {
        return [
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
            [
                "scheduleRule" => [
                    "frequency" => ScheduleRule::WEEKLY,
                    "startDate" => "2023-09-27",
                    "repeatPeriod" => 1,
                    "intervalTime" => '06:00',
                    "weekDays" => '1,4', // 1 (monday) to 7 (sunday)
                ],
                "from" => "2024-08-29 06:00:00", // a Thursday
                "expected" => "2024-08-29 06:00:00", // the Thursday
            ],
            [
                "scheduleRule" => [
                    "frequency" => ScheduleRule::WEEKLY,
                    "startDate" => "2023-09-27",
                    "repeatPeriod" => 1,
                    "intervalTime" => '06:00',
                    "weekDays" => '1,4', // 1 (monday) to 7 (sunday)
                ],
                "from" => "2024-08-28 06:00:00", // a wednesday
                "expected" => "2024-08-29 06:00:00", // next Thursday
            ],
            [
                "scheduleRule" => [
                    "frequency" => ScheduleRule::WEEKLY,
                    "startDate" => "2023-09-27",
                    "repeatPeriod" => 1,
                    "intervalTime" => '06:00',
                    "weekDays" => '1,4', // 1 (monday) to 7 (sunday)
                ],
                "from" => "2024-08-26 07:00:00", // a wednesday
                "expected" => "2024-08-29 06:00:00", // next Thursday
            ],
            [
                "scheduleRule" => [
                    "frequency" => ScheduleRule::WEEKLY,
                    "startDate" => "2023-09-27",
                    "repeatPeriod" => 1,
                    "intervalTime" => '06:00',
                    "weekDays" => '1', // 1 (monday) to 7 (sunday)
                ],
                "from" => "2024-08-26 07:00:00", // a wednesday
                "expected" => "2024-09-02 06:00:00", // next Thursday
            ],


            [
                "scheduleRule" => [
                    "frequency" => ScheduleRule::WEEKLY,
                    "startDate" => "2023-11-23",
                    "repeatPeriod" => 1,
                    "intervalTime" => '14:00',
                    "weekDays" => '4', // 1 (monday) to 7 (sunday)
                ],
                "from" => "2024-08-26 07:00:00", // a wednesday
                "expected" => "2024-08-29 14:00:00", // next Thursday
            ],
            [
                "scheduleRule" => [
                    "frequency" => ScheduleRule::WEEKLY,
                    "startDate" => "2023-11-23",
                    "repeatPeriod" => 1,
                    "intervalTime" => '14:00',
                    "weekDays" => '4', // 1 (monday) to 7 (sunday)
                ],
                "from" => "2024-08-29 07:00:00", // a wednesday
                "expected" => "2024-08-29 14:00:00", // next Thursday
            ],
            [
                "scheduleRule" => [
                    "frequency" => ScheduleRule::WEEKLY,
                    "startDate" => "2023-11-23",
                    "repeatPeriod" => 1,
                    "intervalTime" => '14:00',
                    "weekDays" => '4', // 1 (monday) to 7 (sunday)
                ],
                "from" => "2024-08-29 15:00:00", // a wednesday
                "expected" => "2024-09-05 14:00:00", // next Thursday
            ],
            [
                "scheduleRule" => [
                    "frequency" => ScheduleRule::WEEKLY,
                    "startDate" => "2023-11-23",
                    "repeatPeriod" => 1,
                    "intervalTime" => '14:00',
                    "weekDays" => '4', // 1 (monday) to 7 (sunday)
                ],
                "from" => "2024-08-30 14:00:00", // a wednesday
                "expected" => "2024-09-05 14:00:00", // next Thursday
            ],
        ];
    }

    private function monthlyScheduleRulesProvider(): array {
        return [
            [
                "scheduleRule" => [
                    "frequency" => ScheduleRule::MONTHLY,
                    "startDate" => "2024-08-01",
                    "months" => "8", // 1 (January) to 12 (December)
                    "intervalTime" => '17:00',
                    "monthDays" => '1', // 1, 15 or ScheduleRule::LAST_DAY_OF_WEEK
                ],
                "from" => "2024-07-22 17:00:00",
                "expected" => "2024-08-01 17:00:00",
            ],
            [
                "scheduleRule" => [
                    "frequency" => ScheduleRule::MONTHLY,
                    "startDate" => "2024-08-01",
                    "months" => "8", // 1 (January) to 12 (December)
                    "intervalTime" => '17:00',
                    "monthDays" => '1', // 1, 15 or ScheduleRule::LAST_DAY_OF_WEEK
                ],
                "from" => "2024-08-01 16:00:00",
                "expected" => "2024-08-01 17:00:00",
            ],
            [
                "scheduleRule" => [
                    "frequency" => ScheduleRule::MONTHLY,
                    "startDate" => "2024-08-01",
                    "months" => "8", // 1 (January) to 12 (December)
                    "intervalTime" => '17:00',
                    "monthDays" => '1', // 1, 15 or ScheduleRule::LAST_DAY_OF_WEEK
                ],
                "from" => "2024-08-01 17:01:00",
                "expected" => "2025-08-01 17:00:00",
            ],
            [
                "scheduleRule" => [
                    "frequency" => ScheduleRule::MONTHLY,
                    "startDate" => "2024-08-01",
                    "months" => "8", // 1 (January) to 12 (December)
                    "intervalTime" => '17:00',
                    "monthDays" => '1,15', // 1, 15 or ScheduleRule::LAST_DAY_OF_WEEK
                ],
                "from" => "2024-08-01 17:01:00",
                "expected" => "2024-08-15 17:00:00",
            ],
            [
                "scheduleRule" => [
                    "frequency" => ScheduleRule::MONTHLY,
                    "startDate" => "2024-08-01",
                    "months" => "8", // 1 (January) to 12 (December)
                    "intervalTime" => '17:00',
                    "monthDays" => '1,15,last', // 1, 15 or ScheduleRule::LAST_DAY_OF_WEEK
                ],
                "from" => "2024-08-15 17:01:00",
                "expected" => "2024-08-31 17:00:00",
            ],
            [
                "scheduleRule" => [
                    "frequency" => ScheduleRule::MONTHLY,
                    "startDate" => "2024-08-01",
                    "months" => "8", // 1 (January) to 12 (December)
                    "intervalTime" => '17:00',
                    "monthDays" => '15,last', // 1, 15 or ScheduleRule::LAST_DAY_OF_WEEK
                ],
                "from" => "2024-08-31 17:01:00",
                "expected" => "2025-08-15 17:00:00",
            ],
            [
                "scheduleRule" => [
                    "frequency" => ScheduleRule::MONTHLY,
                    "startDate" => "2024-02-01",
                    "months" => "2", // 1 (January) to 12 (December)
                    "intervalTime" => '17:00',
                    "monthDays" => 'last', // 1, 15 or ScheduleRule::LAST_DAY_OF_WEEK
                ],
                "from" => "2024-01-25 17:01:00",
                "expected" => "2024-02-29 17:00:00",
            ],
            [
                "scheduleRule" => [
                    "frequency" => ScheduleRule::MONTHLY,
                    "startDate" => "2025-02-01",
                    "months" => "2", // 1 (January) to 12 (December)
                    "intervalTime" => '17:00',
                    "monthDays" => 'last', // 1, 15 or ScheduleRule::LAST_DAY_OF_WEEK
                ],
                "from" => "2025-01-25 17:01:00",
                "expected" => "2025-02-28 17:00:00",
            ],
            [
                "scheduleRule" => [
                    "frequency" => ScheduleRule::MONTHLY,
                    "startDate" => "2024-02-01",
                    "months" => "3,4,5", // 1 (January) to 12 (December)
                    "intervalTime" => '17:00',
                    "monthDays" => '1,15,last', // 1, 15 or ScheduleRule::LAST_DAY_OF_WEEK
                ],
                "from" => "2024-01-25 17:01:00",
                "expected" => "2024-03-01 17:00:00",
            ],
            [
                "scheduleRule" => [
                    "frequency" => ScheduleRule::MONTHLY,
                    "startDate" => "2024-03-05",
                    "months" => "3,4,5", // 1 (January) to 12 (December)
                    "intervalTime" => '17:00',
                    "monthDays" => '1,15,last', // 1, 15 or ScheduleRule::LAST_DAY_OF_WEEK
                ],
                "from" => "2024-01-25 17:01:00",
                "expected" => "2024-03-15 17:00:00",
            ],
            [
                "scheduleRule" => [
                    "frequency" => ScheduleRule::MONTHLY,
                    "startDate" => "2024-04-05",
                    "months" => "3", // 1 (January) to 12 (December)
                    "intervalTime" => '17:00',
                    "monthDays" => 'last', // 1, 15 or ScheduleRule::LAST_DAY_OF_WEEK
                ],
                "from" => "2024-01-25 17:01:00",
                "expected" => "2025-03-31 17:00:00",
            ],
            [
                "scheduleRule" => [
                    "frequency" => ScheduleRule::MONTHLY,
                    "startDate" => "2024-03-05",
                    "months" => "3,4", // 1 (January) to 12 (December)
                    "intervalTime" => '17:00',
                    "monthDays" => '1', // 1, 15 or ScheduleRule::LAST_DAY_OF_WEEK
                ],
                "from" => "2024-04-05 17:01:00",
                "expected" => "2025-03-01 17:00:00",
            ],
        ];
    }


}
