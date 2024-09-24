<?php

namespace App\Tests\Service;

use App\Service\DateTimeService;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class DateTimeServiceTest extends KernelTestCase
{
    private DateTimeService $dateTimeService;

    protected function setUp(): void {
        self::bootKernel();
        $container = static::getContainer();

        $this->dateTimeService = $container->get(DateTimeService::class);
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
}
