<?php

namespace App\EventListener;

use App\Annotation\Authenticated;
use App\Entity\Utilisateur;
use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\ORM\EntityManagerInterface;
use ReflectionClass;
use ReflectionException;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpKernel\Event\ControllerArgumentsEvent;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

class AuthenticationListener {

    private $manager;

    public function __construct(EntityManagerInterface $manager) {
        $this->manager = $manager;
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

        $annotation = $reader->getMethodAnnotation($method, Authenticated::class);
        if($annotation instanceof Authenticated) {
            $this->handleAuthenticated($event, $controller, $annotation);
        }
    }

    private function handleAuthenticated(ControllerArgumentsEvent $event, AbstractController $controller, Authenticated $annotation) {
        $request = $event->getRequest();

        if(!method_exists($controller, "setUser")) {
            throw new RuntimeException("Routes annotated with @Authenticated must have a `setUser` method");
        }

        $userRepository = $this->manager->getRepository(Utilisateur::class);

        $authorization = $request->headers->get("x-authorization", "");
        preg_match("/Bearer (\w*)/i", $authorization, $matches);

        $user = $matches ? $userRepository->findOneByApiKey($matches[1]) : null;
        if($user) {
            $controller->setUser($user);
        } else {
            throw new UnauthorizedHttpException("no challenge");
        }
    }

}
