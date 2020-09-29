<?php

namespace App\Service;

use DateTime;
use DateTimeZone;

class UniqueNumberService
{
    public function createUniqueNumber(string $prefix, $lastNumber): string {

        $date = new DateTime('now', new DateTimeZone('Europe/Paris'));
        $dateStr = $date->format('Ymd');

        if ($lastNumber) {
            $lastCounter = (int) substr($lastNumber, -4, 4);
            $currentCounter = ($lastCounter + 1);
        } else {
            $currentCounter = 1;
        }

        $currentCounterStr = (
        $currentCounter < 10 ? ('000' . $currentCounter) :
            ($currentCounter < 100 ? ('00' . $currentCounter) :
                ($currentCounter < 1000 ? ('0' . $currentCounter) :
                    $currentCounter))
        );

        return ($prefix .'-'. $dateStr . $currentCounterStr);
    }
}
