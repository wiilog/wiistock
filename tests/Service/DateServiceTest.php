<?php

namespace App\Tests\Service;

use App\Service\DateService;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class DateServiceTest extends KernelTestCase
{
    private DateService $dateService;

    protected function setUp(): void
    {
        $this->dateService = new DateService();
    }

    /**
     * @dataProvider validTimeProvider
     */
    public function testCalculateMinuteFromWithValidTimes(string $time, int $expectedMinutes): void
    {
        $this->assertEquals($expectedMinutes, $this->dateService->calculateMinuteFrom($time));
    }

    /**
     * @dataProvider invalidTimeProvider
     */
    public function testCalculateMinuteFromWithInvalidTimes(string $time): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->dateService->calculateMinuteFrom($time);
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
        $this->assertEquals(0, $this->dateService->calculateMinuteFrom('00:00'));
        $this->assertEquals(1439, $this->dateService->calculateMinuteFrom('23:59'));
    }

    public function testCalculateMinuteFromWithLeadingZeros(): void
    {
        $this->assertEquals(90, $this->dateService->calculateMinuteFrom('01:30'));
        $this->assertEquals(5, $this->dateService->calculateMinuteFrom('00:05'));
    }

    public function testCalculateMinuteFromUsesCorrectMultiplier(): void
    {
        // Assuming SECONDS_IN_MINUTE is public and equals 60
        $this->assertEquals(
            2 * DateService::SECONDS_IN_MINUTE + 5,
            $this->dateService->calculateMinuteFrom('02:05')
        );
    }
}
