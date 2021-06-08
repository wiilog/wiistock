<?php

namespace App\Controller\IOT;

use App\Annotation\HasPermission;
use App\Entity\Action;
use App\Entity\IOT\Pairing;
use App\Entity\Menu;

use App\Service\DataMonitoringService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/iot/association")
 */
class PairingController extends AbstractController {

    /**
     * @Route("/voir/{pairing}", name="pairing_show")
     * @HasPermission({Menu::IOT, Action::DISPLAY_PAIRING})
     */
    public function show(DataMonitoringService $service, Pairing $pairing): Response {
        return $service->render([
            "title" => "IOT | Associations | Détails",
            "entities" => [$pairing],
        ]);
    }

}

