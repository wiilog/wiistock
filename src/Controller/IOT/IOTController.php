<?php


namespace App\Controller\IOT;


use App\Entity\IOT\Sensor;
use App\Entity\IOT\SensorMessage;
use App\Service\IOT\IOTService;
use Doctrine\ORM\EntityManagerInterface;
use FOS\RestBundle\Controller\AbstractFOSRestController;
use FOS\RestBundle\Controller\Annotations as Rest;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use WiiCommon\Helper\Stream;
use Symfony\Component\Routing\Annotation\Route;


/**
 * Class IOTController
 * @package App\Controller
 */
class IOTController extends AbstractFOSRestController
{

    /**
     * @Rest\Post("/iot")
     * @Rest\View()
     */
    public function postMessage(Request $request,
                               EntityManagerInterface $entityManager,
                               IOTService $IOTService): Response
    {
        if ($request->headers->get('x-api-key') === $_SERVER['APP_IOT_API_KEY']) {
            $frame = json_decode($request->getContent(), true);
            $message = $frame['message'];
            $IOTService->onMessageReceived($message, $entityManager);
            return new Response();
        } else {
            throw new BadRequestHttpException();
        }
    }


    /**
     * @Route("/iot/map-data", name="map_data_api", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     */
    public function getMapData(EntityManagerInterface $entityManager): JsonResponse
    {
        $sensorRepository = $entityManager->getRepository(Sensor::class);
        $messageRepository = $entityManager->getRepository(SensorMessage::class);

        $temperatureSensors = $sensorRepository->findBy([
            'type' => Sensor::GPS_TYPE
        ]);


        $associatedMessages = $messageRepository->findBy(
            ['sensor' => $temperatureSensors],
            ['date' => 'ASC']
        );

        $data = [];

        foreach ($associatedMessages as $message) {
            $date = $message->getDate();
            $sensor = $message->getSensor();

            $dateStr = $date->format('Y-m-d H:i:s');
            $sensorCode = $sensor->getCode();
            if (!isset($data[$sensorCode])) {
                $data[$sensorCode] = [];
            }
            $coordinates = Stream::explode(',', $message->getContent())
                ->map(fn($coordinate) => floatval($coordinate))
                ->toArray();
            $data[$sensorCode][$dateStr] = $coordinates;
        }
        return new JsonResponse($data);
    }

    /**
     * @Route("/iot/chart-data", name="chart_data_api", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     */
    public function getChartData(EntityManagerInterface $entityManager): JsonResponse
    {
        $sensorRepository = $entityManager->getRepository(Sensor::class);
        $messageRepository = $entityManager->getRepository(SensorMessage::class);

        $temperatureSensors = $sensorRepository->findBy([
            'type' => Sensor::TEMP_TYPE
        ]);
        $associatedMessages = $messageRepository->findBy([
            'sensor' => $temperatureSensors
        ], ['date' => 'ASC']);

        $data = [
            'colors' => []
        ];

        foreach ($temperatureSensors as $sensor) {
            srand($sensor->getId());
            $data['colors'][$sensor->getCode()] = sprintf('#%06X', mt_rand(0, 0xFFFFFF));
        }
        srand();

        foreach ($associatedMessages as $message) {
            $date = $message->getDate();
            $sensor = $message->getSensor();

            $dateStr = $date->format('Y-m-d H:i:s');
            $sensorCode = $sensor->getCode();
            if (!isset($data[$dateStr])) {
                $data[$dateStr] = [];
            }
            $data[$dateStr][$sensorCode] = floatval($message->getContent());
        }
        return new JsonResponse($data);
    }

}
