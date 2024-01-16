<?php


namespace App\Controller\IOT;


use App\Entity\IOT\LoRaWANServer;
use App\Service\IOT\IOTService;
use Doctrine\ORM\EntityManagerInterface;
use FOS\RestBundle\Controller\AbstractFOSRestController;
use FOS\RestBundle\Controller\Annotations as Rest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

#[Rest\Route("/api")]
class IOTController extends AbstractFOSRestController
{

    #[Rest\Post("/iot/{loRaWANServer}")]
    #[Rest\Post("/iot")]
    #[Rest\View]
    public function postMessage(Request                 $request,
                                EntityManagerInterface  $entityManager,
                                IOTService              $IOTService,
                                LoRaWANServer           $loRaWANServer = LoRaWANServer::Orange): Response {
        if ($request->headers->get('x-api-key') === $_SERVER['APP_IOT_API_KEY']) {
            $frame = json_decode($request->getContent(), true);
            $message = $frame;
            $IOTService->onMessageReceived($message, $entityManager, $loRaWANServer);
            return new Response();
        } else {
            throw new BadRequestHttpException();
        }
    }

}
