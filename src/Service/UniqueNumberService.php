<?php

namespace App\Service;

use DateTime;
use DateTimeZone;
use Exception;

class UniqueNumberService
{

    const DATE_COUNTER_FORMAT = 'YmdCCCC';

    /**
     * @param string $prefix
     * @param string $format
     * @param string|null $lastNumber
     * @return string
     * @throws Exception
     */
    public function createUniqueNumber(string $prefix,
                                       string $format,
                                       string $lastNumber = null): string {

        $date = new DateTime('now', new DateTimeZone('Europe/Paris'));

        preg_match('/([^C]*)(C+)/', $format, $matches);
        if (empty($matches)) {
            throw new Exception('Invalid number format');
        }

        $dateFormat = $matches[1];
        $counterFormat = $matches[2];
        $counterLen = strlen($counterFormat);

        $lastCounter = (
            (!empty($lastNumber) && $counterLen >= strlen($lastNumber))
                ? (int) substr($lastNumber, -$counterLen, $counterLen)
                : 0
        );

        $currentCounterStr = sprintf("%0{$counterLen}u", $lastCounter + 1);
        $dateStr = !empty($dateFormat) ? $date->format($dateFormat) : '';
        $smartPrefix = !empty($prefix) ? ($prefix . '-') : '';

        return ($smartPrefix . $dateStr . $currentCounterStr);
    }
}
