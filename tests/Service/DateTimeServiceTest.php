<?php

namespace App\Tests\Service;

use App\Service\CacheService;
use App\Service\DateTimeService;
use DateInterval;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class DateTimeServiceTest extends KernelTestCase
{
    private DateTimeService $dateTimeService;
    private EntityManagerInterface $entityManager;
    private CacheService $cacheService;
    private const LOCAL_PATH_CACHE_DAY = 'work-period';
    private const FILE_NAME_WORKED_DAY = 'workedDay';
    private const FILE_NAME_FREE_DAY = 'workFreeDay';
    private const ARRAY_WORKED_PERIOD = [
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
    private const ARRAY_WORKED_PERIOD_BROKEN = [
        "monday" => [
            ["14:00","16:00"],
            ["08:00","10:00"]
        ],
    ];
    private const ARRAY_WORKED_PERIOD_EMPTY = [];

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

    public function workedPeriodBetweenDatesDataProvider(): array {
        return [
            [self::ARRAY_WORKED_PERIOD, $this->arrayFreePeriodEmpty(), "2024-10-08 08:00:00", "2024-10-08 17:00:00", "PT8H"],
            [self::ARRAY_WORKED_PERIOD, $this->arrayFreePeriodEmpty(), "2024-10-11 08:00:00", "2024-10-11 19:00:00", "PT9H"],
            [self::ARRAY_WORKED_PERIOD, $this->arrayFreePeriodEmpty(), "2024-10-07 08:00:00", "2024-10-07 18:00:00", "PT10H"],
            [self::ARRAY_WORKED_PERIOD, $this->arrayFreePeriodEmpty(), "2024-10-08 17:00:00", "2024-10-08 08:00:00", "PT8H"],
            [self::ARRAY_WORKED_PERIOD, $this->arrayFreePeriodEmpty(), "2024-10-08 08:00:00", "2024-10-08 08:00:00", "PT0H"],
            [self::ARRAY_WORKED_PERIOD, $this->arrayFreePeriodEmpty(), "2024-10-10 08:00:00", "2024-10-10 17:00:00", "PT0H"],
            [self::ARRAY_WORKED_PERIOD, $this->arrayFreePeriod(), "2024-10-08 08:00:00", "2024-10-08 17:00:00", "PT0H"],
            [self::ARRAY_WORKED_PERIOD, $this->arrayFreePeriodEmpty(), "2024-10-10 08:00:00", "2024-10-11 10:00:00", "PT2H"],
            [self::ARRAY_WORKED_PERIOD, $this->arrayFreePeriod(), "2024-10-08 08:00:00", "2024-10-09 10:00:00", "PT2H"],
            [self::ARRAY_WORKED_PERIOD_EMPTY, $this->arrayFreePeriodEmpty(), "2024-10-08 08:00:00", "2024-10-08 17:00:00", "PT0H"],
            [self::ARRAY_WORKED_PERIOD, $this->arrayFreePeriodEmpty(), "2024-10-08 18:00:00", "2024-10-09 08:00:00", "PT0H"],
            [self::ARRAY_WORKED_PERIOD, $this->arrayFreePeriodEmpty(), "2024-10-08 21:00:00", "2024-10-08 23:00:00", "PT0H"],
            [self::ARRAY_WORKED_PERIOD, $this->arrayFreePeriodEmpty(), "2024-10-08 21:00:00", "2024-10-09 23:00:00", "PT8H"],
            [self::ARRAY_WORKED_PERIOD, $this->arrayFreePeriodEmpty(), "2024-10-08 07:00:00", "2024-10-16 23:00:00", "PT51H"],
            [self::ARRAY_WORKED_PERIOD, $this->arrayFreePeriod(), "2024-10-08 07:00:00", "2024-10-16 23:00:00", "PT43H"],
            [self::ARRAY_WORKED_PERIOD, $this->arrayFreePeriodEmpty(), "2024-10-08 13:00:00", "2024-10-09 10:00:00", "PT6H"],
            [self::ARRAY_WORKED_PERIOD, $this->arrayFreePeriodEmpty(), "2024-10-08 10:00:00", "2024-10-08 14:00:00", "PT3H"],
            [self::ARRAY_WORKED_PERIOD, $this->arrayFreePeriodEmpty(), "2024-10-07 10:00:00", "2024-10-07 14:00:00", "PT4H"],
            [self::ARRAY_WORKED_PERIOD_BROKEN, $this->arrayFreePeriodEmpty(), "2024-10-07 08:00:00", "2024-10-07 18:00:00", "PT4H"],
            [self::ARRAY_WORKED_PERIOD, $this->arrayFreePeriodEmpty(), "2024-10-08 10:30:00", "2024-10-09 11:30:00", "PT9H"],
            [self::ARRAY_WORKED_PERIOD, $this->arrayFreePeriodEmpty(), "2024-10-08 10:00:00", "2024-10-16 14:00:00", "PT46H"],
            [self::ARRAY_WORKED_PERIOD, $this->arrayFreePeriodEmpty(), "2024-10-08 08:21:00", "2024-10-09 15:37:00", "PT14H16M"],
        ];
    }

    public function addWorkedPeriodToDateTimeDataProvider(): array {
        return [
            [self::ARRAY_WORKED_PERIOD, $this->arrayFreePeriodEmpty(), "2024-10-08 08:00:00", "PT5H", "2024-10-08 14:00:00"],
            [self::ARRAY_WORKED_PERIOD, $this->arrayFreePeriodEmpty(), "2024-10-11 08:00:00", "PT8H", "2024-10-11 18:00:00"],
            [self::ARRAY_WORKED_PERIOD, $this->arrayFreePeriodEmpty(), "2024-10-07 08:00:00", "PT5H", "2024-10-07 13:00:00"],
            [self::ARRAY_WORKED_PERIOD, $this->arrayFreePeriodEmpty(), "2024-10-08 08:00:00", "PT9H", "2024-10-09 09:00:00"],
            [self::ARRAY_WORKED_PERIOD, $this->arrayFreePeriodEmpty(), "2024-10-09 08:00:00", "PT9H", "2024-10-11 09:00:00"],
            [self::ARRAY_WORKED_PERIOD, $this->arrayFreePeriod(), "2024-10-07 08:00:00", "PT11H", "2024-10-09 09:00:00"],
            [self::ARRAY_WORKED_PERIOD, $this->arrayFreePeriod(), "2024-10-08 08:00:00", "PT0H", "2024-10-08 08:00:00"],
            [self::ARRAY_WORKED_PERIOD, $this->arrayFreePeriodEmpty(), "2024-10-08 08:00:00", "PT0H", "2024-10-08 08:00:00"],
            [self::ARRAY_WORKED_PERIOD_EMPTY, $this->arrayFreePeriodEmpty(), "2024-10-08 08:00:00", "PT5H", null],
            [self::ARRAY_WORKED_PERIOD, $this->arrayFreePeriodEmpty(), "2024-10-09 18:00:00", "PT11H", "2024-10-14 10:00:00"],
            [self::ARRAY_WORKED_PERIOD, $this->arrayFreePeriod(), "2024-10-07 18:00:00", "PT10H", "2024-10-11 10:00:00"],
            [self::ARRAY_WORKED_PERIOD, $this->arrayFreePeriodEmpty(), "2024-10-08 19:00:00", "PT2H", "2024-10-09 10:00:00"],
            [self::ARRAY_WORKED_PERIOD, $this->arrayFreePeriodEmpty(), "2024-10-08 07:00:00", "PT51H", "2024-10-16 17:00:00"],
            [self::ARRAY_WORKED_PERIOD, $this->arrayFreePeriod(), "2024-10-08 07:00:00", "PT43H", "2024-10-16 17:00:00"],
            [self::ARRAY_WORKED_PERIOD, $this->arrayFreePeriodEmpty(), "2024-10-08 08:00:00", "PT2S", "2024-10-08 08:00:02"],
            [self::ARRAY_WORKED_PERIOD, $this->arrayFreePeriodEmpty(), "2024-10-08 08:21:00", "PT14H16M", "2024-10-09 15:37:00"],
        ];
    }

    /**
     * @return void
     */
    protected function setUp(): void {
        self::bootKernel();
        $container = static::getContainer();
        $this->dateTimeService = $container->get(DateTimeService::class);
        $this->cacheService = $container->get(CacheService::class);
        $this->entityManager = $container->get(EntityManagerInterface::class);
    }

    /**
     * @testDox Test calculate minute from with valid times
     * @dataProvider validTimeProvider
     */
    public function testCalculateMinuteFromWithValidTimes(string $time, int $expectedMinutes): void
    {
        $this->assertEquals($expectedMinutes, $this->dateTimeService->calculateMinuteFrom($time));
    }

    /**
     * @testDox Test calculate minute from with invalid times
     * @dataProvider invalidTimeProvider
     */
    public function testCalculateMinuteFromWithInvalidTimes(string $time): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->dateTimeService->calculateMinuteFrom($time);
    }

    /**
     * @testDox Test calculate minute from with edge cases
     */
    public function testCalculateMinuteFromWithEdgeCases(): void
    {
        $this->assertEquals(0, $this->dateTimeService->calculateMinuteFrom('00:00'));
        $this->assertEquals(1439, $this->dateTimeService->calculateMinuteFrom('23:59'));
    }

    /**
     * @testDox Test calculate minute from with leading zeros
     */
    public function testCalculateMinuteFromWithLeadingZeros(): void
    {
        $this->assertEquals(90, $this->dateTimeService->calculateMinuteFrom('01:30'));
        $this->assertEquals(5, $this->dateTimeService->calculateMinuteFrom('00:05'));
    }

    /**
     * @testDox Test get worked period
     * @dataProvider workedPeriodBetweenDatesDataProvider
     */
    public function testGetWorkedPeriod(array $arrayWordedDay, array $arrayFreeDay, string $dateString1, string $dateString2, string $expected): void
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

    /**
     * @testDox Test add worked period
     * @dataProvider addWorkedPeriodToDateTimeDataProvider
     */
    public function testAddWorkedPeriod(array $arrayWordedDay, array $arrayFreeDay, $dateString, $dateStringInterval, ?string $expected): void
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
        $this->cacheService->set(self::LOCAL_PATH_CACHE_DAY, self::FILE_NAME_WORKED_DAY, $arrayWordedDay);
        $this->cacheService->set(self::LOCAL_PATH_CACHE_DAY, self::FILE_NAME_FREE_DAY, $arrayFreeDay);
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
