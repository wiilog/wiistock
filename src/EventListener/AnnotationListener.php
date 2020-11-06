<?php

namespace App\EventListener;

use App\Annotation\Authenticated;
use App\Entity\Utilisateur;
use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\ORM\EntityManagerInterface;
use ReflectionClass;
use ReflectionException;
use RuntimeException;
use Symfony\Component\HttpKernel\Event\ControllerArgumentsEvent;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

class AnnotationListener {

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

        foreach($reader->getMethodAnnotations($method) as $annotation) {
            if ($annotation instanceof Authenticated) {
                $this->handleAuthenticated($event, $annotation);
            }
        }
    }

    private function handleAuthenticated(ControllerArgumentsEvent $event, Authenticated $annotation) {
        [$controller] = $event->getController();
        $request = $event->getRequest();

        if(!method_exists($controller, "setUser")) {
            throw new RuntimeException("Routes annotated with @Authenticated must have a `setUser` method");
        }

        if($annotation->getValue() == Authenticated::MOBILE) {
            $userRepository = $this->manager->getRepository(Utilisateur::class);

            $key = $request->get("apiKey");
            $user = $userRepository->findOneByApiKey($key);
            if($user) {
                $controller->setUser($user);
            } else {
                throw new UnauthorizedHttpException("no challenge");
            }
        }
    }

}
