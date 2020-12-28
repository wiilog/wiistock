<?php

namespace App\EventListener;

use App\Annotation\RestAuthenticated;
use App\Annotation\RestVersionChecked;
use App\Entity\Utilisateur;
use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\ORM\EntityManagerInterface;
use ReflectionClass;
use ReflectionException;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpKernel\Event\ControllerArgumentsEvent;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Composer\Semver\Semver;

class RestSecurityListener {

    private $entityManager;
    private $mobileVersion;

    public function __construct(EntityManagerInterface $entityManager,
                                string $mobileVersion) {
        $this->entityManager = $entityManager;
        $this->mobileVersion = $mobileVersion;
    }

    public function onRequest(ControllerArgumentsEvent $event) {
        if(!$event->isMasterRequest() || !is_array($event->getController())) {
            return;
        }

        $reader = new AnnotationReader();
        [$controller, $method] = $event->getController();

        try {
            $class = new ReflectionClass($controller);
            $method = $class->getMethod($method);
        } catch(ReflectionException $e) {
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
    }

    private function handleRestAuthenticated(ControllerArgumentsEvent $event,
                                             AbstractController $controller) {
        $request = $event->getRequest();

        if(!method_exists($controller, "setUser")) {
            throw new RuntimeException("Routes annotated with @Authenticated must have a `setUser` method");
        }

        $userRepository = $this->entityManager->getRepository(Utilisateur::class);

        $authorization = $request->headers->get("x-authorization", "");
        preg_match("/Bearer (\w*)/i", $authorization, $matches);

        $user = $matches ? $userRepository->findOneByApiKey($matches[1]) : null;
        if($user) {
            $controller->setUser($user);
        } else {
            throw new UnauthorizedHttpException("no challenge");
        }
    }

    private function handleRestVersionChecked(ControllerArgumentsEvent $event) {
        $request = $event->getRequest();
        $clientVersion = $request->headers->get("x-app-version", "");

        if(!$clientVersion || !Semver::satisfies($clientVersion, $this->mobileVersion)) {
            throw new UnauthorizedHttpException("no challenge");
        }
    }

}
