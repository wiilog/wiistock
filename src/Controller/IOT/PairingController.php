<?php

namespace App\Controller\IOT;

use App\Annotation\HasPermission;
use App\Entity\Action;
use App\Entity\Article;
use App\Entity\Emplacement;
use App\Entity\IOT\Pairing;
use App\Entity\IOT\Sensor;
use App\Entity\Menu;

use App\Entity\OrdreCollecte;
use App\Entity\Pack;
use App\Entity\Preparation;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/iot/associations")
 */
class PairingController extends AbstractController {

    /**
     * @Route("/", name="pairing_index")
     * @HasPermission({Menu::IOT, Action::DISPLAY_SENSOR})
     */
    public function index(): Response {

        return $this->render("pairing/index.html.twig", [
            'categories' => Sensor::CATEGORIES,
            'sensorTypes' => Sensor::SENSORS
        ]);
    }

    /**
     * @Route("/api", name="pairing_api", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::IOT, Action::DISPLAY_SENSOR}, mode=HasPermission::IN_JSON)
     */
    public function api(Request $request, EntityManagerInterface $manager): Response {
        $pairingRepository = $manager->getRepository(Pairing::class);
        $filters = $request->query;

        $queryResult = $pairingRepository->findByParamsAndFilters($filters);

        $pairings = $queryResult["data"];

        $rows = [];
        /** @var Pairing $pairing */
        foreach ($pairings as $pairing) {
            $type = ($pairing->getSensorWrapper() && $pairing->getSensorWrapper()->getSensor())
                    ? $pairing->getSensorWrapper()->getSensor()->getType()
                    : '';

            $elementIcon = "";
            if($pairing->getEntity() instanceof Emplacement) {
                $elementIcon = Sensor::LOCATION;
            } else if($pairing->getEntity() instanceof Article) {
                $elementIcon = Sensor::ARTICLE;
            } else if($pairing->getEntity() instanceof Pack) {
                $elementIcon = Sensor::PACK;
            } else if($pairing->getEntity() instanceof Preparation) {
                $elementIcon = Sensor::PREPARATION;
            } else if($pairing->getEntity() instanceof OrdreCollecte) {
                $elementIcon = Sensor::COLLECT;
            }

            $rows[] = [
                "type" => $type,
                "typeIcon" => Sensor::SENSORS[$type],
                "name" => $pairing->getSensorWrapper() ? $pairing->getSensorWrapper()->getName() : '',
                "element" => $pairing->getEntity()->__toString(),
                "elementIcon" => $elementIcon
            ];
        }

        return $this->json($rows);
    }
}
