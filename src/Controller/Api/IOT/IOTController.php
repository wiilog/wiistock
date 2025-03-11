<?php


namespace App\Controller\Api\IOT;


use App\Controller\AbstractController;
use App\Entity\IOT\LoRaWANServer;
use App\Service\IOT\IOTService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Annotation\Route;

#[Route("/api")]
class IOTController extends AbstractController {

    #[Route("/iot/{loRaWANServer}", methods: [self::POST])]
    #[Route("/iot", methods: [self::POST])]
    public function postMessage(Request                 $request,
                                EntityManagerInterface  $entityManager,
                                IOTService              $IOTService,
                                LoRaWANServer           $loRaWANServer = LoRaWANServer::Orange): Response {
        if ($request->headers->get('x-api-key') === $_SERVER['APP_IOT_API_KEY']) {
            $frame = json_decode($request->getContent(), true);
            $message = $frame;
            $IOTService->onMessageReceived($message, $entityManager, $loRaWANServer);
            // TODO WIIS-10053 return json response
            return new Response();
        } else {
            throw new BadRequestHttpException();
        }
    }

}
