<?php

namespace App\EventListener;

use App\Annotation\HasPermission;
use App\Annotation\HasValidToken;
use App\Annotation\RestAuthenticated;
use App\Annotation\RestVersionChecked;
use App\Entity\KioskToken;
use App\Entity\Utilisateur;
use App\Service\MobileApiService;
use App\Service\UserService;
use DateTime;
use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\ORM\EntityManagerInterface;
use ReflectionClass;
use ReflectionException;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ControllerArgumentsEvent;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Service\Attribute\Required;
use Twig\Environment;

class AnnotationListener {

    private $entityManager;
    private $userService;
    private $templating;
    private $mobileVersion;
    private $mobileApiService;

    #[Required]
    public RouterInterface $router;

    public function __construct(EntityManagerInterface $entityManager,
                                UserService            $userService,
                                Environment            $templating,
                                string                 $mobileVersion,
                                MobileApiService       $mobileApiService) {
        $this->entityManager = $entityManager;
        $this->userService = $userService;
        $this->templating = $templating;
        $this->mobileVersion = $mobileVersion;
        $this->mobileApiService = $mobileApiService;
    }

    public function onRequest(ControllerArgumentsEvent $event) {
        if (!$event->isMainRequest() || !is_array($event->getController())) {
            return;
        }

        $reader = new AnnotationReader();
        /** @var AbstractController $controller */
        [$controller, $method] = $event->getController();

        if ($controller instanceof AbstractController) {
            /** @var Utilisateur $user */
            $user = $controller->getUser();

            if ($user && !$user->getStatus()) {
                $event->setController(fn() => new RedirectResponse($this->router->generate("logout")));
            }
        }

        try {
            $class = new ReflectionClass($controller);
            $method = $class->getMethod($method);
        } catch (ReflectionException $e) {
            throw new RuntimeException("Failed to read annotation");
        }

        $annotation = $this->getAnnotation($reader, $method, RestVersionChecked::class);
        if ($annotation instanceof RestVersionChecked) {
            $this->handleRestVersionChecked($event);
        }

        $annotation = $this->getAnnotation($reader, $method, RestAuthenticated::class);
        if ($annotation instanceof RestAuthenticated) {
            $this->handleRestAuthenticated($event, $controller);
        }

        $annotation = $this->getAnnotation($reader, $method, HasPermission::class);

        if ($annotation instanceof HasPermission) {
            $this->handleHasPermission($event, $annotation);
        }

        $annotation = $this->getAnnotation($reader, $method, HasValidToken::class);
        if ($annotation instanceof HasValidToken) {
            $this->handleHasValidToken($event);
        }
    }

    private function getAnnotation(AnnotationReader $reader, mixed $method, string $class): mixed {
        $annotation = $reader->getMethodAnnotation($method, $class);
        if($annotation) {
            return $annotation;
        }

        $nativeAnnotations = $method->getAttributes($class);
        if(!empty($nativeAnnotations)) {
            return $nativeAnnotations[0]->newInstance();
        }

        return null;
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
        }
        else {
            throw new UnauthorizedHttpException("no challenge");
        }
    }

    private function handleRestVersionChecked(ControllerArgumentsEvent $event) {
        $request = $event->getRequest();
        $clientVersion = $request->headers->get("x-app-version", "");
        if (!$clientVersion || !$this->mobileApiService->checkMobileVersion($clientVersion, $this->mobileVersion)) {
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
                }
                else if ($annotation->mode == HasPermission::IN_RENDER) {
                    return new Response($this->templating->render("securite/access_denied.html.twig"));
                }
                else {
                    throw new RuntimeException("Unknown mode $annotation->mode");
                }
            });
        }
    }

    private function handleHasValidToken(ControllerArgumentsEvent $event): void {
        $token = $event->getRequest()->get('token');
        $kioskToken = $this->entityManager->getRepository(KioskToken::class)->findOneBy(['token' => $token]);
        $date = new DateTime();

        if (!$kioskToken || $date->diff($kioskToken->getExpireAt())->format("%a") == 0) {
            $event->setController(fn() => new RedirectResponse($this->router->generate("login")));
        }
    }
}
