<?php

namespace App\Entity\Security;

use App\Service\FormatService;

enum AccessTokenTypeEnum: string {
    case SLEEPING_STOCK = 'sleeping_stock';

    /**
     * @return int Token expiration delay in seconds
     */

    public function getExpirationDelay(): int {
        return match ($this) {
            self::SLEEPING_STOCK => 5 * FormatService::SECONDS_IN_DAY
        };
    }
}
