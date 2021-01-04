<?php

namespace App\EventListener;

use App\Annotation\HasPermission;
use App\Annotation\RestAuthenticated;
use App\Annotation\RestVersionChecked;
use App\Entity\Utilisateur;
use App\Service\UserService;
use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\ORM\EntityManagerInterface;
use ReflectionClass;
use ReflectionException;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ControllerArgumentsEvent;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Composer\Semver\Semver;
use Symfony\Component\Security\Core\Security;
use Twig\Environment;

class AnnotationListener {

    private $entityManager;
    private $userService;
    private $templating;
    private $mobileVersion;

    public function __construct(EntityManagerInterface $entityManager,
                                UserService $userService,
                                Environment $templating,
                                string $mobileVersion) {
        $this->entityManager = $entityManager;
        $this->userService = $userService;
        $this->templating = $templating;
        $this->mobileVersion = $mobileVersion;
    }

    public function onRequest(ControllerArgumentsEvent $event) {
        if (!$event->isMasterRequest() || !is_array($event->getController())) {
            return;
        }

        $reader = new AnnotationReader();
        [$controller, $method] = $event->getController();

        try {
            $class = new ReflectionClass($controller);
            $method = $class->getMethod($method);
        } catch (ReflectionException $e) {
            throw new RuntimeException("Failed to read annotation");
        }

        $annotation = $reader->getMethodAnnotation($method, RestVersionChecked::class);
        if ($annotation instanceof RestVersionChecked) {
            $this->handleRestVersionChecked($event);
        }

        $annotation = $reader->getMethodAnnotation($method, RestAuthenticated::class);
        if ($annotation instanceof RestAuthenticated) {
            $this->handleRestAuthenticated($event, $controller);
        }

        $annotation = $reader->getMethodAnnotation($method, HasPermission::class);
        if ($annotation instanceof HasPermission) {
            $this->handleHasPermission($event, $annotation);
        }
    }

    private function handleRestAuthenticated(ControllerArgumentsEvent $event, AbstractController $controller) {
        $request = $event->getRequest();

        if (!method_exists($controller, "setUser")) {
            throw new RuntimeException("Routes annotated with @Authenticated must have a `setUser` method");
        }

        $userRepository = $this->entityManager->getRepository(Utilisateur::class);

        $authorization = $request->headers->get("x-authorization", "");
        preg_match("/Bearer (\w*)/i", $authorization, $matches);

        $user = $matches ? $userRepository->findOneByApiKey($matches[1]) : null;
        if ($user) {
            $controller->setUser($user);
        } else {
            throw new UnauthorizedHttpException("no challenge");
        }
    }

    private function handleRestVersionChecked(ControllerArgumentsEvent $event) {
        $request = $event->getRequest();
        $clientVersion = $request->headers->get("x-app-version", "");

        if (!$clientVersion || !Semver::satisfies($clientVersion, $this->mobileVersion)) {
            throw new UnauthorizedHttpException("no challenge");
        }
    }

    private function handleHasPermission(ControllerArgumentsEvent $event, HasPermission $annotation) {
        if (!$this->userService->hasRightFunction(...$annotation->value)) {
            $event->setController(function() use ($annotation) {
                if ($annotation->mode == HasPermission::IN_JSON) {
                    return new JsonResponse([
                        "success" => false,
                        "msg" => "Accès refusé",
                    ]);
                } else if ($annotation->mode == HasPermission::IN_RENDER) {
                    return new Response($this->templating->render("securite/access_denied.html.twig"));
                } else {
                    throw new \RuntimeException("Unknown mode $annotation->mode");
                }
            });
        }
    }

}
