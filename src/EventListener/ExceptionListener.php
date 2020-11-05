<?php

namespace App\EventListener;

use App\Entity\Utilisateur;
use DateTime;
use Exception;
use Psr\Log\LoggerInterface;
use ReflectionClass;
use ReflectionObject;
use Symfony\Component\ErrorHandler\Exception\FlattenException;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Log\DebugLoggerInterface;
use Symfony\Component\Security\Core\Security;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class ExceptionListener {

    private $security;
    private $client;

    public function __construct(Security $security, HttpClientInterface $client) {
        $this->security = $security;
        $this->client = $client;
    }

    public function onKernelException(ExceptionEvent $event) {
        $user = $this->security->getUser();
        if($user && $user instanceof Utilisateur) {
            $user = [
                "id" => $user->getId(),
                "email" => $user->getEmail(),
                "role" => $user->getRole() ? $user->getRole()->getLabel() : null,
            ];
        }

        $exception = FlattenException::createFromThrowable($event->getThrowable());
        $exceptions = array_merge([$exception], $exception->getAllPrevious());

        foreach($exceptions as $exception) {
            $stacktrace = $exception->getTrace();
            foreach($stacktrace as &$trace) {
                $file = file($trace["file"]);
                array_unshift($file, "");
                $from = max($trace["line"] - 5, 0);
                $to = min($trace["line"] + 5, count($file) - 1);

                $extract = array_slice($file, $from, $to - $from, true);
                foreach($extract as $number => $line) {
                    $extract[$number] = str_replace("\n", "", $line);
                }

                $trace["content"] = $extract;
            }

            $class = new ReflectionClass(FlattenException::class);
            $property = $class->getProperty("trace");
            $property->setAccessible(true);
            $property->setValue($exception, $stacktrace);

            $property = $class->getProperty("previous");
            $property->setAccessible(true);
            $property->setValue($exception, null);
        }

        try {
            $this->client->request("POST", $_SERVER["APP_LOGGER"] ?? "http://logger.follow-gt.fr/api/log", [
                "body" => [
                    "instance" => $_SERVER["APP_INSTANCE"],
                    "context" => [
                        "instance" => $_SERVER["APP_INSTANCE"],
                        "env" => $_SERVER["APP_ENV"],
                        "locale" => $_SERVER["APP_LOCALE"],
                        "client" => $_SERVER["APP_CLIENT"],
                        "dashboard_token" => $_SERVER["APP_DASHBOARD_TOKEN"],
                        "url" => $_SERVER["APP_URL"],
                        "forbidden_phones" => $_SERVER["APP_FORBIDDEN_PHONES"],
                    ],
                    "user" => $user,
                    "request" => serialize($event->getRequest()),
                    "exceptions" => serialize($exceptions),
                    "time" => (new DateTime())->format("d-m-Y H:i:s"),
                ],
            ]);
        } catch(Exception $exception) {

        }
    }

}
