<?php

namespace App\Service;

class MemoryService {
    /**
     * @return positive-int|0 the current memory limit in bytes
     */
    public function getMemoryLimit(): int {
        return $this->getBytes(ini_get('memory_limit'));
    }

    /**
     * @param string $size Value in shorthand memory notation
     * @return positive-int|0 Value in bytes
     */
    public function getBytes(string $size): int {
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
