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

    private array $arrayWorkedPeriod = array(

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
    );
    private array $arrayWorkedPeriodEmpty = array();


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


    private function createCacheWorkedPeriod(array $arrayWordedDay, array $arrayFreeDay,): void {
        file_put_contents($this->localPathCacheWorkedDay, serialize($arrayWordedDay));
        file_put_contents($this->localPathCacheFreeDay, serialize($arrayFreeDay));
    }
    private function deleteCacheWorkedPeriod(): void {
        if(file_exists($this->localPathCacheWorkedDay)) {
            unlink($this->localPathCacheWorkedDay);
        }
        if(file_exists($this->localPathCacheFreeDay)) {
            unlink($this->localPathCacheFreeDay);
        }
    }

    private function arrayFreePeriod() : array
    {
        return array(
            DateTime::createFromFormat('Y-m-d','2024-10-08'),
            DateTime::createFromFormat('Y-m-d','2024-12-25'),
        );
    }

    private function arrayFreePeriodEmpty() : array
    {
        return array();
    }

    /** Get worked period in one day with worked period [08:00 - 12:00;13:00 - 17:00]
    * Must return 28800000 millisecond (8 hours)
    * */
    public function testGetWorkedPeriodBetweenDatesWithBreak() : void
    {
        $this->deleteCacheWorkedPeriod();
        $this->createCacheWorkedPeriod($this->arrayWorkedPeriod, $this->arrayFreePeriodEmpty());
        $date1 = DateTime::createFromFormat('Y-m-d H:i:s','2024-10-08 08:00:00');
        $date2 = DateTime::createFromFormat('Y-m-d H:i:s','2024-10-09 08:00:00');
        $this->assertEquals(
            28800000,
            $this->dateTimeService->convertDateIntervalToMilliseconds($this->dateTimeService->getWorkedPeriodBetweenDates($this->entityManager, $date1, $date2))
        );
    }

    /** Get worked period in one day with worked period [08:00 - 12:00;13:00 - 16:00;17:00 - 19:00]
     * Must return 32400000 millisecond (9 hours)
     * */
    public function testGetWorkedPeriodBetweenDatesWith2Breaks() : void
    {
        $this->deleteCacheWorkedPeriod();
        $this->createCacheWorkedPeriod($this->arrayWorkedPeriod, $this->arrayFreePeriodEmpty());
        $date1 = DateTime::createFromFormat('Y-m-d H:i:s','2024-10-11 08:00:00');
        $date2 = DateTime::createFromFormat('Y-m-d H:i:s','2024-10-12 08:00:00');
        $this->assertEquals(
            32400000,
        $this->dateTimeService->convertDateIntervalToMilliseconds($this->dateTimeService->getWorkedPeriodBetweenDates($this->entityManager, $date1, $date2))
        );
    }

    /** Get worked period in one day with worked period [08:00 - 18:00]
     * Must return 36000000 millisecond (10 hours)
     * */
    public function testGetWorkedPeriodBetweenDatesWithoutBreak() : void
    {
        $this->deleteCacheWorkedPeriod();
        $this->createCacheWorkedPeriod($this->arrayWorkedPeriod, $this->arrayFreePeriodEmpty());
        $date1 = DateTime::createFromFormat('Y-m-d H:i:s','2024-10-07 08:00:00');
        $date2 = DateTime::createFromFormat('Y-m-d H:i:s','2024-10-08 08:00:00');
        $this->assertEquals(
            36000000,
            $this->dateTimeService->convertDateIntervalToMilliseconds($this->dateTimeService->getWorkedPeriodBetweenDates($this->entityManager, $date1, $date2))
        );
    }

    /** Get worked period between two unversed dates with worked period [08:00 - 12:00;13:00 - 17:00]
     * Must return 28800000 millisecond (8 hours)
     * */
    public function testGetWorkedPeriodBetweenUnversedDates() : void
    {
        $this->deleteCacheWorkedPeriod();
        $this->createCacheWorkedPeriod($this->arrayWorkedPeriod, $this->arrayFreePeriodEmpty());
        $date1 = DateTime::createFromFormat('Y-m-d H:i:s','2024-10-09 08:00:00');
        $date2 = DateTime::createFromFormat('Y-m-d H:i:s','2024-10-08 08:00:00');
        $this->assertEquals(
            28800000,
            $this->dateTimeService->convertDateIntervalToMilliseconds($this->dateTimeService->getWorkedPeriodBetweenDates($this->entityManager, $date1, $date2))
        );
    }

    /** Get worked period between same date and same time with worked period [08:00 - 12:00;13:00 - 17:00]
     * Must return 0 millisecond
     * */
    public function testGetWorkedPeriodBetweenSameDates() : void
    {
        $this->deleteCacheWorkedPeriod();
        $this->createCacheWorkedPeriod($this->arrayWorkedPeriod, $this->arrayFreePeriodEmpty());
        $date1 = DateTime::createFromFormat('Y-m-d H:i:s','2024-10-08 08:00:00');
        $date2 = DateTime::createFromFormat('Y-m-d H:i:s','2024-10-08 08:00:00');
        $this->assertEquals(
            0,
            $this->dateTimeService->convertDateIntervalToMilliseconds($this->dateTimeService->getWorkedPeriodBetweenDates($this->entityManager, $date1, $date2))
        );
    }

    /** Get worked period between date during unworked period (thursday)
     * Must return 0 millisecond
     * */
    public function testGetWorkedPeriodBetweenDatesDuringUnworkedDay() : void
    {
        $this->deleteCacheWorkedPeriod();
        $this->createCacheWorkedPeriod($this->arrayWorkedPeriod, $this->arrayFreePeriodEmpty());
        $date1 = DateTime::createFromFormat('Y-m-d H:i:s','2024-10-10 08:00:00');
        $date2 = DateTime::createFromFormat('Y-m-d H:i:s','2024-10-11 08:00:00');
        $this->assertEquals(
            0,
            $this->dateTimeService->convertDateIntervalToMilliseconds($this->dateTimeService->getWorkedPeriodBetweenDates($this->entityManager, $date1, $date2))
        );
    }

    /** Get worked period between date during free day period (2024-10-08)
     * Must return 0 millisecond
     * */
    public function testGetWorkedPeriodBetweenDatesWithFreeDay() : void
    {
        $this->deleteCacheWorkedPeriod();
        $this->createCacheWorkedPeriod($this->arrayWorkedPeriod, $this->arrayFreePeriod());
        $date1 = DateTime::createFromFormat('Y-m-d H:i:s','2024-10-08 08:00:00');
        $date2 = DateTime::createFromFormat('Y-m-d H:i:s','2024-10-09 08:00:00');
        $this->assertEquals(
            0,
            $this->dateTimeService->convertDateIntervalToMilliseconds($this->dateTimeService->getWorkedPeriodBetweenDates($this->entityManager, $date1, $date2))
        );
    }

    /** Get worked period between date without worked day
     * Must return 0 millisecond
     * */
    public function testGetWorkedPeriodBetweenDatesWithoutWorkedDay() : void
    {
        $this->deleteCacheWorkedPeriod();
        $this->createCacheWorkedPeriod($this->arrayWorkedPeriodEmpty, $this->arrayFreePeriodEmpty());
        $date1 = DateTime::createFromFormat('Y-m-d H:i:s','2024-10-08 08:00:00');
        $date2 = DateTime::createFromFormat('Y-m-d H:i:s','2024-10-09 08:00:00');
        $this->assertEquals(
            0,
            $this->dateTimeService->convertDateIntervalToMilliseconds($this->dateTimeService->getWorkedPeriodBetweenDates($this->entityManager, $date1, $date2))
        );
    }

    /** Add worked period of 5 hours at date with worked period [08:00 - 12:00;13:00 - 17:00]
     * Must return same day at 14:00:00
     * */
    public function testAddWorkedIntervalToDateTimeWithBreak() : void
    {
        $this->deleteCacheWorkedPeriod();
        $this->createCacheWorkedPeriod($this->arrayWorkedPeriod, $this->arrayFreePeriodEmpty());
        $date = DateTime::createFromFormat('Y-m-d H:i:s','2024-10-08 08:00:00');
        $dateinterval = new DateInterval('PT5H');
        $this->assertEquals("2024-10-08 14:00:00",$this->dateTimeService->addWorkedPeriodToDateTime($this->entityManager, $date, $dateinterval)->format("Y-m-d H:i:s"));

    }

    /** Add worked period 5 hours at date with worked period [08:00 - 18:00]
     * Must return same day at 13:00:00
     * */
    public function testAddWorkedIntervalToDateTimeWithoutBreak() : void
    {
        $this->deleteCacheWorkedPeriod();
        $this->createCacheWorkedPeriod($this->arrayWorkedPeriod, $this->arrayFreePeriodEmpty());
        $date = DateTime::createFromFormat('Y-m-d H:i:s','2024-10-07 08:00:00');
        $dateinterval = new DateInterval('PT5H');
        $this->assertEquals("2024-10-07 13:00:00",$this->dateTimeService->addWorkedPeriodToDateTime($this->entityManager, $date, $dateinterval)->format("Y-m-d H:i:s"));

    }

    /** Add worked period 9 hours at date with worked period [08:00 - 12:00;13:00 - 17:00]
     * Must return next day at 09:00:00
     * */
    public function testAddWorkedMoreThanDayHoursIntervalToDateTime() : void
    {
        $this->deleteCacheWorkedPeriod();
        $this->createCacheWorkedPeriod($this->arrayWorkedPeriod, $this->arrayFreePeriodEmpty());
        $date = DateTime::createFromFormat('Y-m-d H:i:s','2024-10-08 08:00:00');
        $dateinterval = new DateInterval('PT9H');
        $this->assertEquals("2024-10-09 09:00:00",$this->dateTimeService->addWorkedPeriodToDateTime($this->entityManager, $date, $dateinterval)->format("Y-m-d H:i:s"));

    }

    /** Add worked period 9 hours at date with next day as unworked period (thursday)
     * Must skip one day (thursday) and return friday at 09:00:00
     * */
    public function testAddWorkedIntervalToDateTimeDuringUnworkedDay() : void
    {
        $this->deleteCacheWorkedPeriod();
        $this->createCacheWorkedPeriod($this->arrayWorkedPeriod, $this->arrayFreePeriodEmpty());
        $date = DateTime::createFromFormat('Y-m-d H:i:s','2024-10-09 08:00:00');
        $dateinterval = new DateInterval('PT9H');
        $this->assertEquals("2024-10-11 09:00:00", $this->dateTimeService->addWorkedPeriodToDateTime($this->entityManager, $date, $dateinterval)->format("Y-m-d H:i:s"));
    }

    /** Add worked period 11 hours at date with next day as free period (2024-10-08)
     * Must skip one day (2024-10-08) and return 2024-10-09 at 09:00:00
     * */
    public function testAddWorkedIntervalToDateTimeDuringFreeDay() : void
    {
        $this->deleteCacheWorkedPeriod();
        $this->createCacheWorkedPeriod($this->arrayWorkedPeriod, $this->arrayFreePeriod());
        $date = DateTime::createFromFormat('Y-m-d H:i:s','2024-10-07 08:00:00');
        $dateinterval = new DateInterval('PT11H');
        $this->assertEquals("2024-10-09 09:00:00", $this->dateTimeService->addWorkedPeriodToDateTime($this->entityManager, $date, $dateinterval)->format("Y-m-d H:i:s"));
    }
}
