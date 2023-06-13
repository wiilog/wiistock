<?php


namespace App\Controller\IOT;


use App\Service\IOT\IOTService;
use Doctrine\ORM\EntityManagerInterface;
use FOS\RestBundle\Controller\AbstractFOSRestController;
use FOS\RestBundle\Controller\Annotations as Rest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;


/**
 * Class IOTController
 * @package App\Controller
 */
class IOTController extends AbstractFOSRestController
{

    /**
     * @Rest\Post("/api/iot")
     * @Rest\View()
     */
    public function postMessage(Request $request,
                               EntityManagerInterface $entityManager,
                               IOTService $IOTService): Response
    {
        if ($request->headers->get('x-api-key') === $_SERVER['APP_IOT_API_KEY']) {
            $frame = json_decode($request->getContent(), true);
            $message = $frame;
            $IOTService->onMessageReceived($message, $entityManager);
            return new Response();
        } else {
            throw new BadRequestHttpException();
        }
    }

}
