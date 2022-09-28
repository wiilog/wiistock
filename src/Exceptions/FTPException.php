<?php

namespace App\Exceptions;

use RuntimeException;

class FTPException extends RuntimeException {

    public const UNABLE_TO_CONNECT = 1;
    public const INVALID_LOGINS = 2;
    public const UPLOAD_FAILED = 3;
    public const UNKNOWN_ERROR = 4;

    public const MESSAGES = [
        self::UNABLE_TO_CONNECT => "Le serveur FTP est inacessible à cette adresse et ce port",
        self::INVALID_LOGINS => "L'utilisateur ou le mot de passe du serveur FTP ne sont pas valides",
        self::UPLOAD_FAILED => "Le fichier n'a pas pu être envoyé par manque de place ou dossier inaccessible",
        self::UNKNOWN_ERROR => "Une erreur est survenue lors de l'envoi du fichier",
    ];

    public function __construct(int $code) {
        parent::__construct(self::MESSAGES[$code], $code);
    }

}
