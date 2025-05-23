<?php


namespace App\Service;

use App\Entity\Utilisateur;
use DateTime;
use Psr\Log\LoggerInterface;
use ReflectionClass;
use Symfony\Component\ErrorHandler\Exception\FlattenException;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Throwable;

class ExceptionLoggerService {
    private Serializer $serializer;

    public function __construct(
        private HttpClientInterface $client,
        private LoggerInterface     $symfonyLogger,
        private Security            $security,
        private RequestStack        $requestStack,
    ) {
        $encoder = new JsonEncoder();
        $defaultContext = [
            AbstractNormalizer::CIRCULAR_REFERENCE_HANDLER => function ($object) {
                return $object->getId();
            },
        ];
        $normalizer = new ObjectNormalizer(null, null, null, null, null, null, $defaultContext);

        $this->serializer = new Serializer([$normalizer], [$encoder]);
    }

    public function sendLog(Throwable $throwable): void {
        if (!empty($_SERVER["APP_NO_LOGGER"]) ||
            empty($_SERVER["APP_LOGGER"]) ||
            $throwable instanceof NotFoundHttpException ||
            $throwable instanceof AccessDeniedHttpException ||
            $throwable instanceof UnauthorizedHttpException) {
            return;
        }

        $user = $this->security->getUser();
        if ($user && $user instanceof Utilisateur) {
            $user = [
                "id" => $user->getId(),
                "email" => $user->getEmail(),
                "role" => $user->getRole()
                    ? $user->getRole()->getLabel()
                    : null,
            ];
        }

        $throwable = FlattenException::createFromThrowable($throwable);
        $exceptions = array_merge([$throwable], $throwable->getAllPrevious());

        $request = $this->requestStack->getCurrentRequest();

        foreach ($exceptions as $previous) {
            $stacktrace = $previous->getTrace();
            foreach ($stacktrace as &$trace) {
                $file = $trace['file'] ? file($trace["file"]) : [];
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
            $property->setValue($previous, $stacktrace);

            $property = $class->getProperty("previous");
            $property->setAccessible(true);
            $property->setValue($previous, null);
        }

        try {
            $logger = $_SERVER["APP_LOGGER"];
            $url = $_SERVER["APP_URL"] ?? null;
            $instance = $_SERVER["APP_INSTANCE"] ?? $url;

            if ($instance) {
                $this->client->request("POST", $logger, [
                    "body" => [
                        "instance" => $instance,
                        "context" => [
                            "instance" => $instance,
                            "env" => $_SERVER["APP_ENV"] ?? null,
                            "client" => $_SERVER["APP_CLIENT"] ?? null,
                            "dashboard_token" => $_SERVER["APP_DASHBOARD_TOKEN"] ?? null,
                            "url" => $url ?? null,
                            "forbidden_phones" => $_SERVER["APP_FORBIDDEN_PHONES"] ?? null,
                        ],
                        "user" => $user,
                        "request" => $request ? $this->serializer->serialize($this->normalizeRequest($request), "json") : "",
                        "exceptions" => $this->serializer->serialize($exceptions, "json"),
                        "time" => (new DateTime())->format("d-m-Y H:i:s"),
                    ],
                ]);
            }
        }
        catch (Throwable $sendLogException) {
            $this->symfonyLogger->error("ExceptionLoggerService::sendLog - Exception on logger request: " . $sendLogException->getMessage());
            $this->symfonyLogger->error("ExceptionLoggerService::sendLog - Exception thrown: " . $throwable->getMessage() . " " . $throwable->getTraceAsString());
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
            if(is_object($item) && method_exists($item, "getId")) {
                $bag->set($key, $item->getId());
            }
        }
    }

}
