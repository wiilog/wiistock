<?php

namespace App\EventListener;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Twig\Environment as Twig_Environment;
use Twig\Error\LoaderError as Twig_Error_Loader;
use Twig\Error\RuntimeError as Twig_Error_Runtime;
use Twig\Error\SyntaxError as Twig_Error_Syntax;

/**
 * Class MaintenanceListener
 * @package App\EventListener
 */
class MaintenanceListener
{
    /**
     * @var Twig_Environment
     */
    private $templating;

	/**
	 * MaintenanceListener constructor.
	 * @param Twig_Environment $templating
	 */
    public function __construct(Twig_Environment $templating)
    {
        $this->templating = $templating;
    }

	/**
	 * @param RequestEvent $event
	 * @throws Twig_Error_Loader
	 * @throws Twig_Error_Runtime
	 * @throws Twig_Error_Syntax
	 */
    public function onKernelRequest(RequestEvent $event) {
        $maintenanceView = $this->templating->render('securite/maintenance.html.twig');
        $response = new Response(
            $maintenanceView,
            Response::HTTP_OK,
            array('content-type' => 'text/html')
        );
        $event->setResponse($response);
        $event->stopPropagation();
    }
}
