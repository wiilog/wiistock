<?php

namespace App\Tests\Service;

use App\Service\CacheService;
use App\Service\DateTimeService;
use DateInterval;
use DateTime;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Flex\Cache;

class DateTimeServiceTest extends KernelTestCase
{
    private const Local_Path_Cache_Day = 'work-period';
    private const File_Name_Worked_Day = 'workedDay';
    private const File_Name_Free_Day = 'workFreeDay';
    public const Array_Worked_Period = [

        "monday" => [
            ["08:00","18:00"],
        ],
        "tuesday" => [
            ["08:00","12:00"],
            ["13:00","17:00"]
        ],
        "wednesday" => [
            ["08:00","12:00"],
            ["13:00","17:00"]
        ],
        "friday" => [
            ["08:00","12:00"],
            ["13:00","16:00"],
            ["17:00","19:00"]
        ],
    ] ;
    public const Array_Worked_Period_Broken = [

        "monday" => [
            ["14:00","16:00"],
            ["08:00","10:00"]
        ],
    ];
    private const Array_Worked_Period_Empty = [];
    private DateTimeService $dateTimeService;
    private EntityManagerInterface $entityManager;
    private CacheService $cacheService;

    protected function setUp(): void {
        self::bootKernel();
        $container = static::getContainer();
        $this->dateTimeService = $container->get(DateTimeService::class);
        $this->cacheService = $container->get(CacheService::class);
        $this->entityManager = $container->get(EntityManagerInterface::class);
    }

    /**
     * @dataProvider validTimeProvider
     */
    public function testCalculateMinuteFromWithValidTimes(string $time, int $expectedMinutes): void
    {
        $this->assertEquals($expectedMinutes, $this->dateTimeService->calculateMinuteFrom($time));
    }

    /**
     * @dataProvider invalidTimeProvider
     */
    public function testCalculateMinuteFromWithInvalidTimes(string $time): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->dateTimeService->calculateMinuteFrom($time);
    }

    public function validTimeProvider(): array
    {
        return [
            ['00:00', 0],
            ['01:00', 60],
            ['00:01', 1],
            ['01:30', 90],
            ['12:00', 720],
            ['23:59', 1439],
            ['15:45', 945],
            ['20:15', 1215],
            ['07:30', 450],
            ['18:00', 1080],
        ];
    }

    public function invalidTimeProvider(): array
    {
        return [
            ['24:00'],
            ['25:00'],
            ['12:60'],
            ['1:30'],
            ['01:3'],
            ['1:3'],
            ['-01:30'],
            ['12:00:00'],
            ['noon'],
            ['12h30'],
            [''],
        ];
    }

    public function testCalculateMinuteFromWithEdgeCases(): void
    {
        $this->assertEquals(0, $this->dateTimeService->calculateMinuteFrom('00:00'));
        $this->assertEquals(1439, $this->dateTimeService->calculateMinuteFrom('23:59'));
    }

    public function testCalculateMinuteFromWithLeadingZeros(): void
    {
        $this->assertEquals(90, $this->dateTimeService->calculateMinuteFrom('01:30'));
        $this->assertEquals(5, $this->dateTimeService->calculateMinuteFrom('00:05'));
    }

    public function testCalculateMinuteFromUsesCorrectMultiplier(): void
    {
        // Assuming SECONDS_IN_MINUTE is public and equals 60
        $this->assertEquals(
            2 * DateTimeService::SECONDS_IN_MINUTE + 5,
            $this->dateTimeService->calculateMinuteFrom('02:05')
        );
    }

    /** Get worked period in one day with worked period [08:00 - 12:00;13:00 - 17:00]
    * Must return 28800000 millisecond (8 hours)
    * */
    public function testGetWorkedPeriodBetweenDatesWithBreak(): void
    {
        $this->testGetWorkedPeriod(
            $this::Array_Worked_Period, $this->arrayFreePeriodEmpty(), '2024-10-08 08:00:00', '2024-10-08 17:00:00',
            "PT8H"
        );
    }

    /** Get worked period in one day with worked period [08:00 - 12:00;13:00 - 16:00;17:00 - 19:00]
     * Must return 32400000 millisecond (9 hours)
     * */
    public function testGetWorkedPeriodBetweenDatesWith2Breaks(): void
    {
        $this->testGetWorkedPeriod(
            $this::Array_Worked_Period, $this->arrayFreePeriodEmpty(), '2024-10-11 08:00:00', '2024-10-11 19:00:00',
            "PT9H"
        );
    }

    /** Get worked period in one day with worked period [08:00 - 18:00]
     * Must return 36000000 millisecond (10 hours)
     * */
    public function testGetWorkedPeriodBetweenDatesWithoutBreak(): void
    {
        $this->testGetWorkedPeriod(
            $this::Array_Worked_Period, $this->arrayFreePeriodEmpty(), '2024-10-07 08:00:00', '2024-10-07 18:00:00',
            "PT10H"
        );
    }

    /** Get worked period between two unversed dates with worked period [08:00 - 12:00;13:00 - 17:00]
     * Must return 28800000 millisecond (8 hours)
     * */
    public function testGetWorkedPeriodBetweenUnversedDates(): void
    {
        $this->testGetWorkedPeriod(
            $this::Array_Worked_Period, $this->arrayFreePeriodEmpty(), '2024-10-08 17:00:00', '2024-10-08 08:00:00',
            "PT8H"
        );
    }

    /** Get worked period between same date and same time with worked period [08:00 - 12:00;13:00 - 17:00]
     * Must return 0 millisecond
     * */
    public function testGetWorkedPeriodBetweenSameDates(): void
    {
        $this->testGetWorkedPeriod(
            $this::Array_Worked_Period, $this->arrayFreePeriodEmpty(), '2024-10-08 08:00:00', '2024-10-08 08:00:00',
            "PT0H"
        );
    }

    /** Get worked period between date during unworked period (thursday)
     * Must return 0 millisecond
     * */
    public function testGetWorkedPeriodBetweenDatesDuringUnworkedDay(): void
    {
        $this->testGetWorkedPeriod(
            $this::Array_Worked_Period, $this->arrayFreePeriodEmpty(), '2024-10-10 08:00:00', '2024-10-10 17:00:00',
            "PT0H"
        );
    }

    /** Get worked period between date during free day period (2024-10-08)
     * Must return 0 millisecond
     * */
    public function testGetWorkedPeriodBetweenDatesWithFreeDay(): void
    {
        $this->testGetWorkedPeriod(
            $this::Array_Worked_Period, $this->arrayFreePeriod(), '2024-10-08 08:00:00', '2024-10-08 17:00:00',
            "PT0H"
        );
    }

    /** Get worked period between date during unworked period (thursday) and next day
     * Must return 7200000 millisecond (2 hours)
     * */
    public function testGetWorkedPeriodBetweenDatesUnworkedDayAndWork(): void
    {
        $this->testGetWorkedPeriod(
            $this::Array_Worked_Period, $this->arrayFreePeriodEmpty(), '2024-10-10 08:00:00', '2024-10-11 10:00:00',
            "PT2H"
        );
    }

    /** Get worked period between date during free day period (2024-10-08) and next day
     * Must return 7200000 millisecond (2 hours)
     * */
    public function testGetWorkedPeriodBetweenDatesWithFreeDayAndWork(): void
    {
        $this->testGetWorkedPeriod(
            $this::Array_Worked_Period, $this->arrayFreePeriod(), '2024-10-08 08:00:00', '2024-10-09 10:00:00',
            "PT2H"
        );
    }

    /** Get worked period between date without worked day
     * Must return 0 millisecond
     * */
    public function testGetWorkedPeriodBetweenDatesWithoutWorkedDay(): void
    {
        $this->testGetWorkedPeriod(
            $this::Array_Worked_Period_Empty, $this->arrayFreePeriodEmpty(), '2024-10-08 08:00:00', '2024-10-08 17:00:00',
            "PT0H"
        );
    }


    /** Get worked period between date between 2 days when there are not worked hour
     * Must return 0 millisecond
     * */
    public function testGetWorkedPeriodBetweenDatesBetween2Days(): void
    {
        $this->testGetWorkedPeriod(
            $this::Array_Worked_Period, $this->arrayFreePeriodEmpty(), '2024-10-08 18:00:00', '2024-10-09 08:00:00',
            "PT0H"
        );
    }

    /** Get worked period between date between 2 hour after end of day at same day
     * Must return 0 millisecond
     * */
    public function testGetWorkedPeriodBetweenDatesHourWithoutWork(): void
    {
        $this->testGetWorkedPeriod(
            $this::Array_Worked_Period, $this->arrayFreePeriodEmpty(), '2024-10-08 21:00:00', '2024-10-08 23:00:00',
            "PT0H"
        );
    }

    /** Get worked period between date between 2 hour after end of day at two different day
     * Must return 28800000 millisecond (8 hours)
     * */
    public function testGetWorkedPeriodBetweenDatesHourWithWork(): void
    {
        $this->testGetWorkedPeriod(
            $this::Array_Worked_Period, $this->arrayFreePeriodEmpty(), '2024-10-08 21:00:00', '2024-10-09 23:00:00',
            "PT8H"
        );
    }

    /** Get worked period between date in different week
     * Must return 183600000 millisecond (51 hours)
     * */
    public function testGetWorkedPeriodBetweenDatesDifferentWeek(): void
    {
        $this->testGetWorkedPeriod(
            $this::Array_Worked_Period, $this->arrayFreePeriodEmpty(), '2024-10-08 07:00:00', '2024-10-16 23:00:00',
            "PT51H"
        );

    }

    /** Get worked period between date in different week
     * Must return 183600000 millisecond (51 hours)
     * */
    public function testGetWorkedPeriodBetweenDatesDifferentWeekWithFree(): void
    {
        $this->testGetWorkedPeriod(
            $this::Array_Worked_Period, $this->arrayFreePeriod(), '2024-10-08 07:00:00', '2024-10-16 23:00:00',
            "PT43H"
        );
    }

    /** Get worked period between date between 2 half days
     * Must return 21600000 millisecond (6 hours)
     * */
    public function testGetWorkedPeriodBetweenDates2HalfDays(): void
    {
        $this->testGetWorkedPeriod(
            $this::Array_Worked_Period, $this->arrayFreePeriodEmpty(), '2024-10-08 13:00:00', '2024-10-09 10:00:00',
            "PT6H"
        );
    }

    /** Get worked period between date between 2 hour in same day with break
     * Must return 10800000 millisecond (3 hours)
     * */
    public function testGetWorkedPeriodBetweenDates2hoursInDayWithBreak(): void
    {
        $this->testGetWorkedPeriod(
            $this::Array_Worked_Period, $this->arrayFreePeriodEmpty(), '2024-10-08 10:00:00', '2024-10-08 14:00:00',
            "PT3H"
        );
    }

    /** Get worked period between date between 2 hour in same day without break
     * Must return 14400000 millisecond (4 hours)
     * */
    public function testGetWorkedPeriodBetweenDates2hoursInDayWithoutBreak(): void
    {
        $this->testGetWorkedPeriod(
            $this::Array_Worked_Period, $this->arrayFreePeriodEmpty(), '2024-10-07 10:00:00', '2024-10-07 14:00:00',
            "PT4H"
        );
    }

    /** Get worked period between date with worked period [14:00 - 16:00;08:00 - 10:00]
     * Must return 14400000 millisecond (4 hours)
     * */
    public function testGetWorkedPeriodBetweenDatesBrokenWorkedDay(): void
    {
        $this->testGetWorkedPeriod(
            $this::Array_Worked_Period_Broken, $this->arrayFreePeriodEmpty(), '2024-10-07 08:00:00', '2024-10-07 18:00:00',
            "PT4H"
        );
    }

    /** Get worked period between date 1 delay hour and different day
     * Must return 32400000 millisecond (9 hours)
     * */
    public function testGetWorkedPeriodBetweenDates1HourDiffDay(): void
    {
        $this->testGetWorkedPeriod(
            $this::Array_Worked_Period, $this->arrayFreePeriodEmpty(), '2024-10-08 10:30:00', '2024-10-09 11:30:00',
            "PT9H"
        );
    }

    /** Get worked period between date 1 delay day and different week
     * Must return 14400000 millisecond (46 hours)
     * */
    public function testGetWorkedPeriodBetweenDates1DayDiffWeek(): void
    {
        $this->testGetWorkedPeriod(
            $this::Array_Worked_Period, $this->arrayFreePeriodEmpty(), '2024-10-08 10:00:00', '2024-10-16 14:00:00',
            "PT46H"
        );
    }

    /** Get worked period between date with hour not full
     * Must return 50496000 millisecond (14 hours and 16 minutes)
     * */
    public function testGetWorkedPeriodBetweenDatesStrangeHour(): void
    {
        $this->testGetWorkedPeriod(
            $this::Array_Worked_Period, $this->arrayFreePeriodEmpty(), '2024-10-08 08:21:00', '2024-10-09 15:37:00',
            "PT14H16M"
        );
    }

    /** Add worked period of 5 hours at date with worked period [08:00 - 12:00;13:00 - 17:00]
     * Must return same day at 14:00:00
     * */
    public function testAddWorkedIntervalToDateTimeWithBreak(): void
    {
        $this->testAddWorkedPeriod(
            $this::Array_Worked_Period, $this->arrayFreePeriodEmpty(), '2024-10-08 08:00:00', 'PT5H',
            "2024-10-08 14:00:00"
        );
    }

    /** Add worked period of 8 hours at date with worked period [08:00 - 12:00;13:00 - 16:00;17:00 - 19:00]
     * Must return same day at 18:00:00
     * */
    public function testAddWorkedIntervalToDateTimeWith2Breaks(): void
    {
        $this->testAddWorkedPeriod(
            $this::Array_Worked_Period, $this->arrayFreePeriodEmpty(), '2024-10-11 08:00:00', 'PT8H',
            "2024-10-11 18:00:00"
        );
    }

    /** Add worked period 5 hours at date with worked period [08:00 - 18:00]
     * Must return same day at 13:00:00
     * */
    public function testAddWorkedIntervalToDateTimeWithoutBreak(): void
    {
        $this->testAddWorkedPeriod(
            $this::Array_Worked_Period, $this->arrayFreePeriodEmpty(), '2024-10-07 08:00:00', 'PT5H',
            "2024-10-07 13:00:00"
        );

    }

    /** Add worked period 9 hours at date with worked period [08:00 - 12:00;13:00 - 17:00]
     * Must return next day at 09:00:00
     * */
    public function testAddWorkedMoreThanDayHoursIntervalToDateTime(): void
    {
        $this->testAddWorkedPeriod(
            $this::Array_Worked_Period, $this->arrayFreePeriodEmpty(), '2024-10-08 08:00:00', 'PT9H',
            "2024-10-09 09:00:00"
        );
    }

    /** Add worked period 9 hours at date with next day as unworked period (thursday)
     * Must skip one day (thursday) and return friday at 09:00:00
     * */
    public function testAddWorkedIntervalToDateTimeDuringUnworkedDay(): void
    {
        $this->testAddWorkedPeriod(
            $this::Array_Worked_Period, $this->arrayFreePeriodEmpty(), '2024-10-09 08:00:00', 'PT9H',
            "2024-10-11 09:00:00"
        );
    }

    /** Add worked period 11 hours at date with next day as free period (2024-10-08)
     * Must skip one day (2024-10-08) and return 2024-10-09 at 09:00:00
     * */
    public function testAddWorkedIntervalToDateTimeDuringFreeDay(): void
    {
        $this->testAddWorkedPeriod(
            $this::Array_Worked_Period, $this->arrayFreePeriod(), '2024-10-07 08:00:00', 'PT11H',
            "2024-10-09 09:00:00"
        );
    }

    /** Add worked period 0 hours at date with day as free period (2024-10-08)
     * Must stay in same day
     * */
    public function testAdd0WorkedIntervalToDateTimeWithFreeDay(): void
    {
        $this->testAddWorkedPeriod(
            $this::Array_Worked_Period, $this->arrayFreePeriod(), '2024-10-08 08:00:00', 'PT0H',
            "2024-10-08 08:00:00"
        );
    }

    /** Add worked period 0 hours at date with worked period [08:00 - 12:00;13:00 - 17:00]
     * Must stay in same day
     * */
    public function testAdd0WorkedIntervalToDateTime(): void
    {
        $this->testAddWorkedPeriod(
            $this::Array_Worked_Period, $this->arrayFreePeriodEmpty(), '2024-10-08 08:00:00', 'PT0H',
            "2024-10-08 08:00:00"
        );
    }

    /** Add worked period 5 hours at date without worked day
     * Must return null
     * */
    public function testAddWorkedIntervalToDateTimeWithoutWorkedDay(): void
    {
        $this->testAddWorkedPeriod(
            $this::Array_Worked_Period_Empty, $this->arrayFreePeriodEmpty(), '2024-10-08 08:00:00', 'PT5H',
            null
        );
    }

    /** Add worked period 11 hours at date with next day as unworked day and all another work day after
     * Must skip two day and weekend
     * */
    public function testAddWorkedIntervalToDateTimeSkipUnworkedDay(): void
    {
        $this->testAddWorkedPeriod(
            $this::Array_Worked_Period, $this->arrayFreePeriodEmpty(), '2024-10-09 18:00:00', 'PT11H',
            "2024-10-14 10:00:00"
        );
    }

    /** Add worked period 12 hours at date with next day as free day and all another work day after
     * Must skip two days
     * */
    public function testAddWorkedIntervalToDateTimeSkipFreeDay(): void
    {
        $this->testAddWorkedPeriod(
            $this::Array_Worked_Period, $this->arrayFreePeriod(), '2024-10-07 18:00:00', 'PT10H',
            "2024-10-11 10:00:00"
        );
    }

    /** Add worked period 2 hour after end of day
     * Must skip two day and weekend
     * */
    public function testAddWorkedIntervalToDateTimeAfterEndOfDay(): void
    {
        $this->testAddWorkedPeriod(
            $this::Array_Worked_Period, $this->arrayFreePeriodEmpty(), '2024-10-08 19:00:00', 'PT2H',
            "2024-10-09 10:00:00"
        );
    }

    /** Add worked period 51h
     * Must return another week (correct week)
     * */
    public function testAddWorkedIntervalToDateTimeBigAdd(): void
    {
        $this->testAddWorkedPeriod(
            $this::Array_Worked_Period, $this->arrayFreePeriodEmpty(), '2024-10-08 07:00:00', 'PT51H',
            "2024-10-16 17:00:00"
        );
    }

    /** Add worked period 51h with free day
     * Must return another week (correct week)
     * */
    public function testAddWorkedIntervalToDateTimeBigAddWithFreeDay(): void
    {
        $this->testAddWorkedPeriod(
            $this::Array_Worked_Period, $this->arrayFreePeriod(), '2024-10-08 07:00:00', 'PT43H',
            "2024-10-16 17:00:00"
        );
    }

    /** Add worked period 2s
     * Must return same day more 2 second
     * */
    public function testAddWorkedIntervalToDateTimeLittleAdd(): void
    {
        $this->testAddWorkedPeriod(
            $this::Array_Worked_Period, $this->arrayFreePeriodEmpty(), '2024-10-08 08:00:00', 'PT2S',
            "2024-10-08 08:00:02"
        );
    }

    /** Add worked period 14h16
     * Must return next day with hour not full
     * */
    public function testAddWorkedIntervalToDateTimeStrangeHour(): void
    {
        $this->testAddWorkedPeriod(
            $this::Array_Worked_Period, $this->arrayFreePeriodEmpty(), '2024-10-08 08:21:00', 'PT14H16M',
            "2024-10-09 15:37:00"
        );
    }

    private function testGetWorkedPeriod(array $arrayWordedDay, array $arrayFreeDay, string $dateString1, string $dateString2, string $expected): void
    {
        $this->createCacheWorkedPeriod($arrayWordedDay, $arrayFreeDay);

        $date1 = DateTime::createFromFormat('Y-m-d H:i:s', $dateString1);
        $date2 = DateTime::createFromFormat('Y-m-d H:i:s', $dateString2);
        $expectedDateInterval = $this->dateTimeService->convertDateIntervalToMilliseconds(new DateInterval($expected));
        $testedDateInterval = $this->dateTimeService->convertDateIntervalToMilliseconds($this->dateTimeService->getWorkedPeriodBetweenDates($this->entityManager, $date1, $date2));

        $this->assertEquals(
            $expectedDateInterval,
            $testedDateInterval
        );
    }

    private function testAddWorkedPeriod(array $arrayWordedDay, array $arrayFreeDay, $dateString, $dateStringInterval, ?string $expected): void
    {
        $this->createCacheWorkedPeriod($arrayWordedDay, $arrayFreeDay);

        $date = DateTime::createFromFormat('Y-m-d H:i:s', $dateString);
        $dateInterval = new DateInterval($dateStringInterval);
        $testedDate = $this->dateTimeService->addWorkedPeriodToDateTime($this->entityManager, $date, $dateInterval);

        $this->assertEquals(
            $expected,
            $testedDate?->format("Y-m-d H:i:s")
        );
    }

    private function createCacheWorkedPeriod(array $arrayWordedDay, array $arrayFreeDay): void
    {
        $this->cacheService->set($this::Local_Path_Cache_Day, $this::File_Name_Worked_Day, $arrayWordedDay);
        $this->cacheService->set($this::Local_Path_Cache_Day, $this::File_Name_Free_Day, $arrayFreeDay);
    }

    private function arrayFreePeriod(): array
    {
        return [
            DateTime::createFromFormat('Y-m-d','2024-10-08'),
            DateTime::createFromFormat('Y-m-d','2024-12-25'),
        ];
    }

    private function arrayFreePeriodEmpty(): array
    {
        return [];
    }
}
