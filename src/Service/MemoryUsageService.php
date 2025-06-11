<?php

namespace App\Service;

class MemoryUsageService {

    private const DEFAULT_OVERCONSUMPTION_THRESHOLD = 0.9;

    /**
     * @var int Memory limit config in php ini conf
     */
    private int $memoryLimit;

    public function __construct() {
        $this->memoryLimit = $this->getMemoryLimit();
    }

    /**
     * Return true if current memory usage is near the limit with the threshold applied.
     */
    public function isMemoryOverconsumptionOngoing(int $threshold = self::DEFAULT_OVERCONSUMPTION_THRESHOLD): bool {
        return ($this->memoryLimit * $threshold) < memory_get_usage(true);
    }

    /**
     * @return positive-int|0 the current memory limit in bytes
     */
    private function getMemoryLimit(): int {
        return $this->getBytes(ini_get('memory_limit'));
    }

    /**
     * @param string $size Value in shorthand memory notation
     * @return positive-int|0 Value in bytes
     */
    private function getBytes(string $size): int {
        $size = trim($size);

        preg_match('/([0-9]+)[\s]*([a-zA-Z]+)/', $size, $matches);

        $value = (int)($matches[1] ?? 0);
        $metric = strtolower($matches[2] ?? 'b');

        // Note: (1024 ** 2) is the same as (1024 * 1024) or pow(1024, 2)
        $value *= match ($metric) {
            'b', 'byte' => 1,
            'k', 'kb' => 1024,
            'm', 'mb' => (1024 ** 2),
            'g', 'gb' => (1024 ** 3),
            't', 'tb' =>  (1024 ** 4),
            default => 0
        };

        return $value;
    }
}
