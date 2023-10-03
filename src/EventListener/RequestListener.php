<?php

namespace App\EventListener;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Contracts\Service\Attribute\Required;
use Twig\Environment as Twig_Environment;

class RequestListener {

    private const MAINTENANCE_ENV = 'maintenance';

    #[Required]
    public Twig_Environment $templating;

    public function onKernelRequest(RequestEvent $event): void {

        $stopListener = $this->handleMaintenanceEnvironment($event);
        if ($stopListener) {
            return;
        }

        $stopListener = $this->mapRequestData($event);
        if ($stopListener) {
            return;
        }
    }

    private function handleMaintenanceEnvironment(RequestEvent $event): bool {
        if ($_SERVER["APP_ENV"] === self::MAINTENANCE_ENV) {
            $maintenanceView = $this->templating->render('securite/maintenance.html.twig');
            $response = new Response(
                $maintenanceView,
                Response::HTTP_OK,
                array('content-type' => 'text/html')
            );
            $event->setResponse($response);
            $event->stopPropagation();
            return true;
        }

        return false;
    }

    private function mapRequestData(RequestEvent $event): bool {

        $request = $event->getRequest();

        $data = $request->request->all();
        foreach ($data as $key => $datum) {
            if ($datum === '') {
                $request->request->set($key, null);
            }
        }

        return false;
    }
}
