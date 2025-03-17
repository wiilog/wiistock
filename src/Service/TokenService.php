<?php

namespace App\Service;

use Symfony\Contracts\Service\Attribute\Required;

class TokenService {
    public function generateToken($length): string {
        return bin2hex(random_bytes($length));
    }
}
