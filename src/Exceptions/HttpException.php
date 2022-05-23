<?php

namespace App\Exceptions;

use JetBrains\PhpStorm\Pure;
use RuntimeException;

class HttpException extends RuntimeException {

    protected string $userMessage;

    #[Pure]
    public function __construct(string $userMessage, string $error = null) {
        parent::__construct($error ?: $userMessage);
        $this->userMessage = $userMessage;
    }

    public function getUserMessage(): string {
        return $this->userMessage;
    }

}
