<?php

namespace App\Controller\IOT;

use App\Entity\IOT\Sensor;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;


/**
 * @Route("/iot/capteur")
 */
class SensorController extends AbstractController {

    /**
     * @Route("/data", name="get_sensor_data_by_code", options={"expose"=true}, methods={"GET"}, condition="request.isXmlHttpRequest()")
     */
    public function getSensorDataByCode(Request $request,
                                        EntityManagerInterface $entityManager): Response {

        $search = $request->query->get('term');
        $onlyAvailable = $request->query->getBoolean('onlyAvailable');

        $sensorRepository = $entityManager->getRepository(Sensor::class);

        return $this->json([
            'results' => $sensorRepository->getSensorByCode($search, $onlyAvailable)
        ]);
    }
}

