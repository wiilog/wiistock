<?php


namespace App\Service;

use App\Entity\Utilisateur;
use DateTime;
use ReflectionClass;
use Symfony\Component\ErrorHandler\Exception\FlattenException;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Security;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Throwable;

class ExceptionLoggerService {

    const DEFAULT_LOGGER_URL = "http://logger.follow-gt.fr/api/log";

    private $security;
    private $client;

    public function __construct(Security $security, HttpClientInterface $client) {
        $this->security = $security;
        $this->client = $client;
    }

    public function sendLog(Throwable $throwable, Request $request) {
        $env = $_SERVER['APP_ENV'];
        if ($env === 'dev') {
            return;
        }

        $user = $this->security->getUser();
        if ($user && $user instanceof Utilisateur) {
            $user = [
                "id" => $user->getId(),
                "email" => $user->getEmail(),
                "role" => $user->getRole() ? $user->getRole()->getLabel() : null,
            ];
        }

        $throwable = FlattenException::createFromThrowable($throwable);
        $exceptions = array_merge([$throwable], $throwable->getAllPrevious());

        foreach ($exceptions as $throwable) {
            $stacktrace = $throwable->getTrace();
            foreach ($stacktrace as &$trace) {
                $file = file($trace["file"]);
                array_unshift($file, "");
                $from = max($trace["line"] - 5, 0);
                $to = min($trace["line"] + 5, count($file) - 1);

                $extract = array_slice($file, $from, $to - $from, true);
                foreach ($extract as $number => $line) {
                    $extract[$number] = str_replace("\n", "", $line);
                }

                $trace["content"] = $extract;
            }

            $class = new ReflectionClass(FlattenException::class);
            $property = $class->getProperty("trace");
            $property->setAccessible(true);
            $property->setValue($throwable, $stacktrace);

            $property = $class->getProperty("previous");
            $property->setAccessible(true);
            $property->setValue($throwable, null);
        }

        try {
            $appUrl = $_SERVER["APP_URL"] ?? null;
            $instance = $_SERVER["APP_INSTANCE"] ?? $appUrl;
            if ($instance) {
                $this->client->request("POST", $_SERVER["APP_LOGGER"] ?? self::DEFAULT_LOGGER_URL, [
                    "body" => [
                        "instance" => $instance,
                        "context" => [
                            "instance" => $instance,
                            "env" => $_SERVER["APP_ENV"] ?? null,
                            "locale" => $_SERVER["APP_LOCALE"] ?? null,
                            "client" => $_SERVER["APP_CLIENT"] ?? null,
                            "dashboard_token" => $_SERVER["APP_DASHBOARD_TOKEN"] ?? null,
                            "url" => $appUrl ?? null,
                            "forbidden_phones" => $_SERVER["APP_FORBIDDEN_PHONES"] ?? null,
                        ],
                        "user" => $user,
                        "request" => serialize($this->normalizeRequest($request)),
                        "exceptions" => serialize($exceptions),
                        "time" => (new DateTime())->format("d-m-Y H:i:s"),
                    ],
                ]);
            }
        } catch (Throwable $ignored) {

        }
    }

    private function normalizeRequest(Request $request): Request {
        $this->normalize($request->attributes);
        $this->normalize($request->request);
        $this->normalize($request->query);
        $this->normalize($request->server);
        $this->normalize($request->files);
        $this->normalize($request->cookies);

        return $request;
    }

    private function normalize(ParameterBag $bag) {
        foreach($bag->all() as $key => $item) {
            if(method_exists($item, "getId")) {
                $bag->set($key, $item->getId());
            }
        }
    }

}
