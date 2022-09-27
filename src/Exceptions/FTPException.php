<?php

namespace App\Exceptions;

use RuntimeException;

class FTPException extends RuntimeException {

    public const UNABLE_TO_CONNECT = 1;
    public const INVALID_LOGINS = 2;

    public const MESSAGES = [
        self::UNABLE_TO_CONNECT => "Le serveur FTP est inacessible à cette adresse et ce port",
        self::INVALID_LOGINS => "L'utilisateur ou le mot de passe du serveur FTP ne sont pas valides",
    ];

    public function __construct(int $code) {
        parent::__construct(self::MESSAGES[$code], $code);
    }

}
