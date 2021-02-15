<?php


namespace App\Service;


use InvalidArgumentException;

class FormService {

    public function validateDate($value, $errorMessage = ''): void {
        $valueStr = $value ?: '';
        preg_match('/(\d{2})\/(\d{2})\/(\d+)$/', $valueStr, $matches);
        $dayIndex = 1;
        $monthIndex = 2;
        $yearIndex = 3;
        if (empty($matches)
            || count($matches) !== 4
            || !checkdate($matches[$monthIndex], $matches[$dayIndex], $matches[$yearIndex])
            || strlen($matches[$yearIndex]) > 4) {
            throw new InvalidArgumentException($errorMessage);
        }
    }

}
