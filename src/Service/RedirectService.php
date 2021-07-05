<?php

namespace App\Service;

use DateTime;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\RouterInterface;

class RedirectService {

    private $stack;
    private $session;
    private $router;

    public function __construct(RequestStack $stack, SessionInterface $session, RouterInterface $router) {
        $this->stack = $stack;
        $this->session = $session;
        $this->router = $router;
    }

    public function load() {
        $fiveSecondsAgo = new DateTime("-15 seconds");

        $route = $this->stack->getCurrentRequest()->attributes->get("_route");
        $extra = $this->session->remove("__extra_params_$route");

        if($extra && $extra["time"] > $fiveSecondsAgo) {
            return $extra["content"];
        }

        return null;
    }

    public function redirect(string $route, array $params, $extra = null) {
        return new RedirectResponse($this->generateUrl($route, $params, $extra));
    }

    public function generateUrl(string $route, array $params, $extra = null) {
        $this->session->set("__extra_params_$route", [
            "time" => new DateTime("now"),
            "content" => $extra,
        ]);

        return $this->router->generate($route, $params);
    }

}
