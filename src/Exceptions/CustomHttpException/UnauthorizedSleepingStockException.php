<?php


namespace App\Exceptions\CustomHttpException;


use Symfony\Component\HttpFoundation\Response;


class UnauthorizedSleepingStockException extends CustomHttpException {

    public function __construct(string $message = '',
                                ?\Throwable $previous = null,
                                array $headers = [],
                                int $code = 0) {
        parent::__construct(Response::HTTP_UNAUTHORIZED, $message, $previous, $headers, $code);
    }

}
