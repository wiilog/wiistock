<?php

namespace App\EventListener;

use App\Annotation\HasPermission;
use App\Annotation\HasValidToken;
use App\Annotation\RestVersionChecked;
use App\Controller\AbstractController;
use App\Entity\Kiosk;
use App\Entity\Utilisateur;
use App\Service\MobileApiService;
use App\Service\UserService;
use DateTime;
use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\ORM\EntityManagerInterface;
use ReflectionClass;
use ReflectionException;
use RuntimeException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ControllerArgumentsEvent;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController as SymfonyAbstractController;
use Symfony\Component\Routing\RouterInterface;
use Twig\Environment;

class AnnotationListener {

    public function __construct(
        private EntityManagerInterface                 $entityManager,
        private UserService                            $userService,
        private Environment                            $templating,
        #[Autowire("%nomade_version%")] private string $mobileVersion,
        private MobileApiService                       $mobileApiService,
        private RouterInterface                        $router,
    ) {
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

        $annotation = $this->getAnnotation($reader, $method, HasPermission::class);

        if ($annotation instanceof HasPermission) {
            $this->handleHasPermission($event, $annotation, $controller);
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

    private function handleRestVersionChecked(ControllerArgumentsEvent $event): void {
        $request = $event->getRequest();
        $clientVersion = $request->headers->get("x-app-version", "");
        if (!$clientVersion || !$this->mobileApiService->checkMobileVersion($clientVersion, $this->mobileVersion)) {
            throw new UnauthorizedHttpException("no challenge");
        }
    }

    private function handleHasPermission(ControllerArgumentsEvent $event, HasPermission $annotation, SymfonyAbstractController $controller): void {
        $parameters =  [...$annotation->value, $controller->getUser()];
        if (!$this->userService->hasRightFunction(...$parameters)) {
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
        $kioskToken = $this->entityManager->getRepository(Kiosk::class)->findOneBy(['token' => $token]);
        $date = new DateTime();

        if (!$kioskToken || $date->diff($kioskToken->getExpireAt())->format("%a") == 0) {
            $event->setController(fn() => new RedirectResponse($this->router->generate("login")));
        }
    }
}
