<?php

namespace App\Tests\Service;

use App\Service\FormatService;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use DateTime;

class FormatServiceTest extends KernelTestCase
{
    private FormatService $formatService;

    protected function setUp(): void
    {
        $this->formatService = new FormatService();
    }

    /**
     * @dataProvider minutesToDatetimeProvider
     */
    public function testMinutesToDatetime(string $minutes, string $expectedTime): void
    {
        $result = $this->formatService->minutesToDatetime($minutes);
        $this->assertInstanceOf(DateTime::class, $result);
        $this->assertEquals($expectedTime, $result->format('H:i'));
    }

    public function minutesToDatetimeProvider(): array
    {
        return [
            ['0', '00:00'],
            ['60', '01:00'],
            ['90', '01:30'],
            ['145', '02:25'],
            ['1440', '00:00'],  // Test full day
            ['1441', '00:01'],  // Test overflow
        ];
    }

    /**
     * @dataProvider datetimeToStringProvider
     */
    public function testDatetimeToString(string $time, string $expected): void
    {
        $date = new DateTime($time);
        $result = $this->formatService->datetimeToString($date);
        $this->assertEquals($expected, $result);
    }

    public function datetimeToStringProvider(): array
    {
        return [
            ['2023-01-01 00:00:00', '0h00'],
            ['2023-01-01 01:30:00', '1h30'],
            ['2023-01-01 12:00:00', '12h00'],
            ['2023-01-01 23:59:00', '23h59'],
            ['2023-01-01 09:05:00', '9h05'],
        ];
    }

    public function testDatetimeToStringWithCustomDateTime(): void
    {
        $date = new DateTime();
        $date->setTime(14, 45);
        $result = $this->formatService->datetimeToString($date);
        $this->assertEquals('14h45', $result);
    }
}
