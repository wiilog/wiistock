<?php


namespace App\Controller\Api\IOT;


use App\Controller\AbstractController;
use App\Entity\IOT\IotServer;
use App\Service\IOT\IOTService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Annotation\Route;

#[Route("/api")]
class IOTController extends AbstractController {

    #[Route("/iot/{server}", methods: [self::POST])]
    #[Route("/iot", methods: [self::POST])]
    public function postMessage(Request                $request,
                                EntityManagerInterface $entityManager,
                                IOTService             $IOTService,
                                IotServer              $server = IotServer::Orange): JsonResponse {
        if ($request->headers->get('x-api-key') === $_SERVER['APP_IOT_API_KEY']) {
            $message = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
            $IOTService->onMessageReceived($message, $entityManager, $server);

            return new JsonResponse(['success' => true]);
        }

        throw new BadRequestHttpException();
    }

}
