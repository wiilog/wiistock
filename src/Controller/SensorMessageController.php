<?php

namespace App\Controller;

use App\Annotation\HasPermission;
use App\Entity\Action;
use App\Entity\IOT\Sensor;
use App\Entity\IOT\SensorWrapper;
use App\Entity\Menu;
use App\Service\SensorMessageService;
use Doctrine\ORM\EntityManagerInterface;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * @Route("iot/capteur")
 */
class SensorMessageController extends AbstractController
{
    /**
     * @Route("/{id}/messages", name="sensor_message_index", options={"expose"=true})
     * @HasPermission({Menu::IOT, Action::DISPLAY_SENSOR})
     */
    public function index($id, EntityManagerInterface $entityManager): Response
    {
        $sensorWrapperRepository = $entityManager->getRepository(SensorWrapper::class);
        return $this->render('sensor_message/index.html.twig', [
            'sensor' => $sensorWrapperRepository->find($id)->getSensor()
        ]);
    }

    /**
     * @Route("/messages/api/{sensor}", name="sensor_messages_api", options={"expose"=true})
     * @HasPermission({Menu::IOT, Action::DISPLAY_SENSOR}, mode=HasPermission::IN_JSON)
     */
    public function api(Sensor $sensor,
                        Request $request,
                        SensorMessageService $sensorMessageService): Response
    {
        $data = $sensorMessageService->getDataForDatatable($sensor, $request->request);
        return new JsonResponse($data);
    }
}
