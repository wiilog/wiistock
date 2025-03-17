<?php

namespace App\Entity\Security;

enum AccessTokenTypeEnum: string {
    case SLEEPING_STOCK = 'sleeping_stock';

    /**
     * @return int Expiration delay in seconds
     */
    public function getExpirationDelay(): int {
        return match ($this) {
            self::SLEEPING_STOCK => 5 * 24 * 60 * 60
        };
    }
}
