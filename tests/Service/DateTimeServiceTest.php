<?php

namespace App\Tests\Service;

use App\Service\DateTimeService;
use DateInterval;
use DateTime;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class DateTimeServiceTest extends KernelTestCase
{
    private DateTimeService $dateTimeService;
    private EntityManagerInterface $entityManager;

    private String $localPathCacheWorkedDay = './cache/work-period/workedDay';

    private String $localPathCacheFreeDay = './cache/work-period/workFreeDay';

    private array $arrayWorkedPeriod = [

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

    private array $arrayWorkedPeriodBroken = [

        "monday" => [
            ["14:00","16:00"],
            ["08:00","10:00"]
        ],
    ];
    private array $arrayWorkedPeriodEmpty = [];


    protected function setUp(): void {
        self::bootKernel();
        $container = static::getContainer();
        $this->dateTimeService = $container->get(DateTimeService::class);
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

    private function testGetWorkedPeriodWithParameter(array $arrayWordedDay, array $arrayFreeDay, $dateString1, $dateString2) : int
    {
        $this->deleteCacheWorkedPeriod();
        $this->createCacheWorkedPeriod($arrayWordedDay, $arrayFreeDay);

        $date1 = DateTime::createFromFormat('Y-m-d H:i:s',$dateString1);
        $date2 = DateTime::createFromFormat('Y-m-d H:i:s',$dateString2);

        return $this->dateTimeService->convertDateIntervalToMilliseconds($this->dateTimeService->getWorkedPeriodBetweenDates($this->entityManager, $date1, $date2));
    }

    private function testAddWorkedPeriodWithParameter(array $arrayWordedDay, array $arrayFreeDay, $dateString, $dateStringInterval) : ?String
    {
        $this->deleteCacheWorkedPeriod();
        $this->createCacheWorkedPeriod($arrayWordedDay, $arrayFreeDay);

        $date = DateTime::createFromFormat('Y-m-d H:i:s',$dateString);
        $dateInterval = new DateInterval($dateStringInterval);

        return $this->dateTimeService->addWorkedPeriodToDateTime($this->entityManager, $date, $dateInterval)?->format("Y-m-d H:i:s");
    }

    private function createCacheWorkedPeriod(array $arrayWordedDay, array $arrayFreeDay): void
    {
        file_put_contents($this->localPathCacheWorkedDay, serialize($arrayWordedDay));
        file_put_contents($this->localPathCacheFreeDay, serialize($arrayFreeDay));
    }
    private function deleteCacheWorkedPeriod(): void
    {
        if(file_exists($this->localPathCacheWorkedDay)) {
            unlink($this->localPathCacheWorkedDay);
        }
        if(file_exists($this->localPathCacheFreeDay)) {
            unlink($this->localPathCacheFreeDay);
        }
    }

    private function arrayFreePeriod() : array
    {
        return [
            DateTime::createFromFormat('Y-m-d','2024-10-08'),
            DateTime::createFromFormat('Y-m-d','2024-12-25'),
        ];
    }

    private function arrayFreePeriodEmpty() : array
    {
        return [];
    }

    /** Get worked period in one day with worked period [08:00 - 12:00;13:00 - 17:00]
    * Must return 28800000 millisecond (8 hours)
    * */
    public function testGetWorkedPeriodBetweenDatesWithBreak() : void
    {
        $this->assertEquals(
            $this->dateTimeService->convertDateIntervalToMilliseconds(new DateInterval("PT8H")),
            $this->testGetWorkedPeriodWithParameter($this->arrayWorkedPeriod, $this->arrayFreePeriodEmpty(), '2024-10-08 08:00:00', '2024-10-08 17:00:00')
        );
    }

    /** Get worked period in one day with worked period [08:00 - 12:00;13:00 - 16:00;17:00 - 19:00]
     * Must return 32400000 millisecond (9 hours)
     * */
    public function testGetWorkedPeriodBetweenDatesWith2Breaks() : void
    {
        $this->assertEquals(
            $this->dateTimeService->convertDateIntervalToMilliseconds(new DateInterval("PT9H")),
            $this->testGetWorkedPeriodWithParameter($this->arrayWorkedPeriod, $this->arrayFreePeriodEmpty(), '2024-10-11 08:00:00', '2024-10-11 19:00:00')
        );
    }

    /** Get worked period in one day with worked period [08:00 - 18:00]
     * Must return 36000000 millisecond (10 hours)
     * */
    public function testGetWorkedPeriodBetweenDatesWithoutBreak() : void
    {
        $this->assertEquals(
            $this->dateTimeService->convertDateIntervalToMilliseconds(new DateInterval("PT10H")),
            $this->testGetWorkedPeriodWithParameter($this->arrayWorkedPeriod, $this->arrayFreePeriodEmpty(), '2024-10-07 08:00:00', '2024-10-07 18:00:00')
        );
    }

    /** Get worked period between two unversed dates with worked period [08:00 - 12:00;13:00 - 17:00]
     * Must return 28800000 millisecond (8 hours)
     * */
    public function testGetWorkedPeriodBetweenUnversedDates() : void
    {
        $this->assertEquals(
            $this->dateTimeService->convertDateIntervalToMilliseconds(new DateInterval("PT8H")),
            $this->testGetWorkedPeriodWithParameter($this->arrayWorkedPeriod, $this->arrayFreePeriodEmpty(), '2024-10-08 17:00:00', '2024-10-08 08:00:00')
        );
    }

    /** Get worked period between same date and same time with worked period [08:00 - 12:00;13:00 - 17:00]
     * Must return 0 millisecond
     * */
    public function testGetWorkedPeriodBetweenSameDates() : void
    {
        $this->assertEquals(
            0,
            $this->testGetWorkedPeriodWithParameter($this->arrayWorkedPeriod, $this->arrayFreePeriodEmpty(), '2024-10-08 08:00:00', '2024-10-08 08:00:00')
        );
    }

    /** Get worked period between date during unworked period (thursday)
     * Must return 0 millisecond
     * */
    public function testGetWorkedPeriodBetweenDatesDuringUnworkedDay() : void
    {
        $this->assertEquals(
            0,
            $this->testGetWorkedPeriodWithParameter($this->arrayWorkedPeriod, $this->arrayFreePeriodEmpty(), '2024-10-10 08:00:00', '2024-10-10 17:00:00')
        );
    }

    /** Get worked period between date during free day period (2024-10-08)
     * Must return 0 millisecond
     * */
    public function testGetWorkedPeriodBetweenDatesWithFreeDay() : void
    {
        $this->assertEquals(
            0,
            $this->testGetWorkedPeriodWithParameter($this->arrayWorkedPeriod, $this->arrayFreePeriod(), '2024-10-08 08:00:00', '2024-10-08 17:00:00')
        );
    }

    /** Get worked period between date during unworked period (thursday) and next day
     * Must return 7200000 millisecond (2 hours)
     * */
    public function testGetWorkedPeriodBetweenDatesUnworkedDayAndWork() : void
    {
        $this->assertEquals(
            $this->dateTimeService->convertDateIntervalToMilliseconds(new DateInterval("PT2H")),
            $this->testGetWorkedPeriodWithParameter($this->arrayWorkedPeriod, $this->arrayFreePeriodEmpty(), '2024-10-10 08:00:00', '2024-10-11 10:00:00')
        );
    }

    /** Get worked period between date during free day period (2024-10-08) and next day
     * Must return 7200000 millisecond (2 hours)
     * */
    public function testGetWorkedPeriodBetweenDatesWithFreeDayAndWork() : void
    {
        $this->assertEquals(
            $this->dateTimeService->convertDateIntervalToMilliseconds(new DateInterval("PT2H")),
            $this->testGetWorkedPeriodWithParameter($this->arrayWorkedPeriod, $this->arrayFreePeriod(), '2024-10-08 08:00:00', '2024-10-09 10:00:00')
        );
    }

    /** Get worked period between date without worked day
     * Must return 0 millisecond
     * */
    public function testGetWorkedPeriodBetweenDatesWithoutWorkedDay() : void
    {
        $this->assertEquals(
            0,
            $this->testGetWorkedPeriodWithParameter($this->arrayWorkedPeriodEmpty, $this->arrayFreePeriodEmpty(), '2024-10-08 08:00:00', '2024-10-08 17:00:00')
        );
    }


    /** Get worked period between date between 2 days when there are not worked hour
     * Must return 0 millisecond
     * */
    public function testGetWorkedPeriodBetweenDatesBetween2Days() : void
    {
        $this->assertEquals(
            0,
            $this->testGetWorkedPeriodWithParameter($this->arrayWorkedPeriod, $this->arrayFreePeriodEmpty(), '2024-10-08 18:00:00', '2024-10-09 08:00:00')
        );
    }

    /** Get worked period between date between 2 hour after end of day at same day
     * Must return 0 millisecond
     * */
    public function testGetWorkedPeriodBetweenDatesHourWithoutWork() : void
    {
        $this->assertEquals(
            0,
            $this->testGetWorkedPeriodWithParameter($this->arrayWorkedPeriod, $this->arrayFreePeriodEmpty(), '2024-10-08 21:00:00', '2024-10-08 23:00:00')
        );
    }

    /** Get worked period between date between 2 hour after end of day at two different day
     * Must return 28800000 millisecond (8 hours)
     * */
    public function testGetWorkedPeriodBetweenDatesHourWithWork() : void
    {
        $this->assertEquals(
            $this->dateTimeService->convertDateIntervalToMilliseconds(new DateInterval("PT8H")),
            $this->testGetWorkedPeriodWithParameter($this->arrayWorkedPeriod, $this->arrayFreePeriodEmpty(), '2024-10-08 21:00:00', '2024-10-09 23:00:00')
        );
    }

    /** Get worked period between date in different week
     * Must return 183600000 millisecond (51 hours)
     * */
    public function testGetWorkedPeriodBetweenDatesDifferentWeek() : void
    {
        $this->assertEquals(
            $this->dateTimeService->convertDateIntervalToMilliseconds(new DateInterval("PT51H")),
            $this->testGetWorkedPeriodWithParameter($this->arrayWorkedPeriod, $this->arrayFreePeriodEmpty(), '2024-10-08 07:00:00', '2024-10-16 23:00:00')
        );
    }

    /** Get worked period between date in different week
     * Must return 183600000 millisecond (51 hours)
     * */
    public function testGetWorkedPeriodBetweenDatesDifferentWeekWithFree() : void
    {
        $this->assertEquals(
            $this->dateTimeService->convertDateIntervalToMilliseconds(new DateInterval("PT43H")),
            $this->testGetWorkedPeriodWithParameter($this->arrayWorkedPeriod, $this->arrayFreePeriod(), '2024-10-08 07:00:00', '2024-10-16 23:00:00')
        );
    }


    /** Get worked period between date between 2 half days
     * Must return 21600000 millisecond (6 hours)
     * */
    public function testGetWorkedPeriodBetweenDates2HalfDays() : void
    {
        $this->assertEquals(
            $this->dateTimeService->convertDateIntervalToMilliseconds(new DateInterval("PT6H")),
            $this->testGetWorkedPeriodWithParameter($this->arrayWorkedPeriod, $this->arrayFreePeriodEmpty(), '2024-10-08 13:00:00', '2024-10-09 10:00:00')
        );
    }

    /** Get worked period between date between 2 hour in same day with break
     * Must return 10800000 millisecond (3 hours)
     * */
    public function testGetWorkedPeriodBetweenDates2hoursInDayWithBreak() : void
    {
        $this->assertEquals(
            $this->dateTimeService->convertDateIntervalToMilliseconds(new DateInterval("PT3H")),
            $this->testGetWorkedPeriodWithParameter($this->arrayWorkedPeriod, $this->arrayFreePeriodEmpty(), '2024-10-08 10:00:00', '2024-10-08 14:00:00')
        );
    }

    /** Get worked period between date between 2 hour in same day without break
     * Must return 14400000 millisecond (4 hours)
     * */
    public function testGetWorkedPeriodBetweenDates2hoursInDayWithoutBreak() : void
    {
        $this->assertEquals(
            $this->dateTimeService->convertDateIntervalToMilliseconds(new DateInterval("PT4H")),
            $this->testGetWorkedPeriodWithParameter($this->arrayWorkedPeriod, $this->arrayFreePeriodEmpty(), '2024-10-07 10:00:00', '2024-10-07 14:00:00')
        );
    }

    /** Get worked period between date with worked period [14:00 - 16:00;08:00 - 10:00]
     * Must return 14400000 millisecond (4 hours)
     * */
    public function testGetWorkedPeriodBetweenDatesBrokenWorkedDay() : void
    {
        $this->assertEquals(
            $this->dateTimeService->convertDateIntervalToMilliseconds(new DateInterval("PT4H")),
            $this->testGetWorkedPeriodWithParameter($this->arrayWorkedPeriodBroken, $this->arrayFreePeriodEmpty(), '2024-10-07 08:00:00', '2024-10-07 18:00:00')
        );
    }

    /** Get worked period between date 1 delay hour and different day
     * Must return 32400000 millisecond (9 hours)
     * */
    public function testGetWorkedPeriodBetweenDates1HourDiffDay() : void
    {
        $this->assertEquals(
            $this->dateTimeService->convertDateIntervalToMilliseconds(new DateInterval("PT9H")),
            $this->testGetWorkedPeriodWithParameter($this->arrayWorkedPeriod, $this->arrayFreePeriodEmpty(), '2024-10-08 10:30:00', '2024-10-09 11:30:00')
        );
    }

    /** Get worked period between date 1 delay day and different week
     * Must return 14400000 millisecond (46 hours)
     * */
    public function testGetWorkedPeriodBetweenDates1DayDiffWeek() : void
    {
        $this->assertEquals(
            $this->dateTimeService->convertDateIntervalToMilliseconds(new DateInterval("PT46H")),
            $this->testGetWorkedPeriodWithParameter($this->arrayWorkedPeriod, $this->arrayFreePeriodEmpty(), '2024-10-08 10:00:00', '2024-10-16 14:00:00')
        );
    }

    /** Get worked period between date with hour not full
     * Must return 50496000 millisecond (14 hours and 16 minutes)
     * */
    public function testGetWorkedPeriodBetweenDatesStrangeHour() : void
    {
        $this->assertEquals(
            $this->dateTimeService->convertDateIntervalToMilliseconds(new DateInterval("PT14H16M")),
            $this->testGetWorkedPeriodWithParameter($this->arrayWorkedPeriod, $this->arrayFreePeriodEmpty(), '2024-10-08 08:21:00', '2024-10-09 15:37:00')
        );
    }

    /** Add worked period of 5 hours at date with worked period [08:00 - 12:00;13:00 - 17:00]
     * Must return same day at 14:00:00
     * */
    public function testAddWorkedIntervalToDateTimeWithBreak() : void
    {
        $this->assertEquals(
            "2024-10-08 14:00:00",
            $this->testAddWorkedPeriodWithParameter($this->arrayWorkedPeriod, $this->arrayFreePeriodEmpty(), '2024-10-08 08:00:00', 'PT5H')
        );
    }

    /** Add worked period of 5 hours at date with worked period [08:00 - 12:00;13:00 - 16:00;17:00 - 19:00]
     * Must return same day at 14:00:00
     * */
    public function testAddWorkedIntervalToDateTimeWith2Breaks() : void
    {
        $this->assertEquals(
            "2024-10-11 18:00:00",
            $this->testAddWorkedPeriodWithParameter($this->arrayWorkedPeriod, $this->arrayFreePeriodEmpty(), '2024-10-11 08:00:00', 'PT8H')
        );
    }

    /** Add worked period 5 hours at date with worked period [08:00 - 18:00]
     * Must return same day at 13:00:00
     * */
    public function testAddWorkedIntervalToDateTimeWithoutBreak() : void
    {
        $this->assertEquals(
            "2024-10-07 13:00:00",
            $this->testAddWorkedPeriodWithParameter($this->arrayWorkedPeriod, $this->arrayFreePeriodEmpty(), '2024-10-07 08:00:00', 'PT5H')
        );

    }

    /** Add worked period 9 hours at date with worked period [08:00 - 12:00;13:00 - 17:00]
     * Must return next day at 09:00:00
     * */
    public function testAddWorkedMoreThanDayHoursIntervalToDateTime() : void
    {
        $this->assertEquals(
            "2024-10-09 09:00:00",
            $this->testAddWorkedPeriodWithParameter($this->arrayWorkedPeriod, $this->arrayFreePeriodEmpty(), '2024-10-08 08:00:00', 'PT9H')
        );
    }

    /** Add worked period 9 hours at date with next day as unworked period (thursday)
     * Must skip one day (thursday) and return friday at 09:00:00
     * */
    public function testAddWorkedIntervalToDateTimeDuringUnworkedDay() : void
    {
        $this->assertEquals(
            "2024-10-11 09:00:00",
            $this->testAddWorkedPeriodWithParameter($this->arrayWorkedPeriod, $this->arrayFreePeriodEmpty(), '2024-10-09 08:00:00', 'PT9H')
        );
    }

    /** Add worked period 11 hours at date with next day as free period (2024-10-08)
     * Must skip one day (2024-10-08) and return 2024-10-09 at 09:00:00
     * */
    public function testAddWorkedIntervalToDateTimeDuringFreeDay() : void
    {
        $this->assertEquals(
            "2024-10-09 09:00:00",
            $this->testAddWorkedPeriodWithParameter($this->arrayWorkedPeriod, $this->arrayFreePeriod(), '2024-10-07 08:00:00', 'PT11H')
        );
    }

    /** Add worked period 0 hours at date with day as free period (2024-10-08)
     * Must stay in same day
     * */
    public function testAdd0WorkedIntervalToDateTimeWithFreeDay() : void
    {
        $this->assertEquals(
            "2024-10-08 08:00:00",
            $this->testAddWorkedPeriodWithParameter($this->arrayWorkedPeriod, $this->arrayFreePeriod(), '2024-10-08 10:00:00', 'PT0H')
        );
    }

    /** Add worked period 0 hours at date with worked period [08:00 - 12:00;13:00 - 17:00]
     * Must stay in same day
     * */
    public function testAdd0WorkedIntervalToDateTime() : void
    {
        $this->assertEquals(
            "2024-10-08 08:00:00",
            $this->testAddWorkedPeriodWithParameter($this->arrayWorkedPeriod, $this->arrayFreePeriodEmpty(), '2024-10-08 08:00:00', 'PT0H')
        );
    }

    /** Add worked period 5 hours at date without worked day
     * Must return null
     * */
    public function testAddWorkedIntervalToDateTimeWithoutWorkedDay() : void
    {
        $this->assertEquals(
            null,
            $this->testAddWorkedPeriodWithParameter($this->arrayWorkedPeriodEmpty, $this->arrayFreePeriodEmpty(), '2024-10-08 08:00:00', 'PT5H')
        );
    }

    /** Add worked period 11 hours at date with next day as unworked day and all another work day after
     * Must skip two day and weekend
     * */
    public function testAddWorkedIntervalToDateTimeSkipUnworkedDay() : void
    {
        $this->assertEquals(
            "2024-10-14 10:00:00",
            $this->testAddWorkedPeriodWithParameter($this->arrayWorkedPeriod, $this->arrayFreePeriodEmpty(), '2024-10-09 18:00:00', 'PT11H')
        );
    }

    /** Add worked period 11 hours at date with next day as free day and all another work day after
     * Must skip two day and weekend
     * */
    public function testAddWorkedIntervalToDateTimeSkipFreeDay() : void
    {
        $this->assertEquals(
            "2024-10-11 10:00:00",
            $this->testAddWorkedPeriodWithParameter($this->arrayWorkedPeriod, $this->arrayFreePeriod(), '2024-10-07 18:00:00', 'PT10H')
        );
    }

    /** Add worked period 2 hour after end of day
     * Must skip two day and weekend
     * */
    public function testAddWorkedIntervalToDateTimeAfterEndOfDay() : void
    {
        $this->assertEquals(
            "2024-10-09 10:00:00",
            $this->testAddWorkedPeriodWithParameter($this->arrayWorkedPeriod, $this->arrayFreePeriodEmpty(), '2024-10-08 19:00:00', 'PT2H')
        );
    }

    /** Add worked period 51h
     * Must return another week (correct week)
     * */
    public function testAddWorkedIntervalToDateTimeBigAdd() : void
    {
        $this->assertEquals(
            "2024-10-16 17:00:00",
            $this->testAddWorkedPeriodWithParameter($this->arrayWorkedPeriod, $this->arrayFreePeriodEmpty(), '2024-10-08 07:00:00', 'PT51H')
        );
    }

    /** Add worked period 51h with free day
     * Must return another week (correct week)
     * */
    public function testAddWorkedIntervalToDateTimeBigAddWithFreeDay() : void
    {
        $this->assertEquals(
            "2024-10-16 17:00:00",
            $this->testAddWorkedPeriodWithParameter($this->arrayWorkedPeriod, $this->arrayFreePeriod(), '2024-10-08 07:00:00', 'PT43H')
        );
    }

    /** Add worked period 2s
     * Must return same day more 2 second
     * */
    public function testAddWorkedIntervalToDateTimeLittleAdd() : void
    {
        $this->assertEquals(
            "2024-10-08 08:00:02",
            $this->testAddWorkedPeriodWithParameter($this->arrayWorkedPeriod, $this->arrayFreePeriodEmpty(), '2024-10-08 08:00:00', 'PT2S')
        );
    }

    /** Add worked period 3h with broken worked day
     * Must return same day more 2 second
     * */
    public function testAddWorkedIntervalToDateTimeBrokenWork() : void
    {
        $this->assertEquals(
            "2024-10-07 15:00:00",
            $this->testAddWorkedPeriodWithParameter($this->arrayWorkedPeriodBroken, $this->arrayFreePeriodEmpty(), '2024-10-07 08:00:00', 'PT3H')
        );
    }

    /** Add worked period 14h16
     * Must return next day with hour not full
     * */
    public function testAddWorkedIntervalToDateTimeStrangeHour() : void
    {
        $this->assertEquals(
            "2024-10-09 15:37:00",
            $this->testAddWorkedPeriodWithParameter($this->arrayWorkedPeriod, $this->arrayFreePeriodEmpty(), '2024-10-08 08:21:00', 'PT14H16M')
        );
    }




}
