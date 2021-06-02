<?php

namespace App\Controller\IOT;

use App\Annotation\HasPermission;
use App\Entity\Action;
use App\Entity\IOT\Sensor;
use App\Entity\Menu;

use App\Service\IOT\PairingService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;


/**
 * @Route("/iot/capteur")
 */
class PairingController extends AbstractController
{
    /**
     * @Route("/{id}/elements-associes", name="pairing_index", options={"expose"=true})
     * @HasPermission({Menu::IOT, Action::DISPLAY_SENSOR})
     */
    public function index($id, EntityManagerInterface $entityManager): Response
    {
        $sensor = $entityManager->getRepository(Sensor::class)->find($id);
        return $this->render('iot/pairing/index.html.twig', [
            'sensor' => $sensor
        ]);
    }

    /**
     * @Route("/elements-associes/api", name="pairing_api", options={"expose"=true}, methods={"POST"}, condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::IOT, Action::DISPLAY_SENSOR})
     */
    public function api(Request $request,
                        PairingService $pairingService,
                        EntityManagerInterface $entityManager): Response {

        $sensorId = $request->query->get('sensor');
        $sensor = $entityManager->getRepository(Sensor::class)->find($sensorId);
        $data = $pairingService->getDataForDatatable($sensor, $request->request);
        return $this->json($data);
    }
}

