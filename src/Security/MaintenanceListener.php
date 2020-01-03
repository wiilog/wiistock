<?php

namespace App\Security;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Bundle\FrameworkBundle\Templating\EngineInterface;
use Twig_Environment;
use Twig\Error\LoaderError as Twig_Error_Loader;
use Twig\Error\RuntimeError as Twig_Error_Runtime;
use Twig\Error\SyntaxError as Twig_Error_Syntax;

/**
 * Class MaintenanceListener
 * @package App\Security
 */
class MaintenanceListener
{
    /**
     * @var EngineInterface
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
	 * @param GetResponseEvent $event
	 * @throws Twig_Error_Loader
	 * @throws Twig_Error_Runtime
	 * @throws Twig_Error_Syntax
	 */
    public function onKernelRequest(GetResponseEvent $event) {
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
