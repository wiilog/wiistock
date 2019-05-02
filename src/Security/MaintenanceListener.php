<?php
/**
 * Created by PhpStorm.
 * User: c.gazaniol
 * Date: 30/04/2019
 * Time: 14:24
 */

namespace App\Security;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Bundle\FrameworkBundle\Templating\EngineInterface;

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
     */
    public function __construct(\Twig_Environment $templating)
    {
        $this->templating = $templating;
    }

    /**
     * @param GetResponseEvent $event
     * @throws \Twig_Error_Loader
     * @throws \Twig_Error_Runtime
     * @throws \Twig_Error_Syntax
     */
    public function onKernelRequest(GetResponseEvent $event)
    {
        $maintenanceView = $this->templating->render(
            'securite/maintenance.html.twig'
        );
        $response = new Response(
            $maintenanceView,
            Response::HTTP_OK,
            array('content-type' => 'text/html')
        );
        $event->setResponse(new Response($response->getContent()));
        $event->stopPropagation();
    }
}