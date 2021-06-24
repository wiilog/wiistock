<?php

namespace App\Controller\IOT;

use App\Annotation\HasPermission;
use App\Entity\Action;
use App\Entity\Article;
use App\Entity\Demande;
use App\Entity\Emplacement;
use App\Entity\IOT\PairedEntity;
use App\Entity\IOT\Sensor;
use App\Entity\LocationGroup;
use App\Entity\Menu;

use App\Entity\OrdreCollecte;
use App\Entity\Pack;
use App\Entity\Preparation;
use App\Service\IOT\DataMonitoringService;
use App\Service\IOT\IOTService;
use App\Service\IOT\PairingService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\RouterInterface;
use WiiCommon\Helper\Stream;

/**
 * @Route("/iot/historique")
 */
class DataHistoryController extends AbstractController {

    /**
     * @Route("/voir", name="show_data_history", options={"expose"=true})
     * @HasPermission({Menu::IOT, Action::DISPLAY_SENSOR})
     */
    public function show(Request $request,
                         EntityManagerInterface $entityManager,
                         IOTService $IOTService,
                         DataMonitoringService $dataMonitoringService): Response {
        $query = $request->query;

        $type = $query->get('type');
        $id = $query->get('id');

        $entity = $IOTService->getEntity($entityManager, $type, $id);

        return $dataMonitoringService->render([
            "title" => $this->getBreadcrumb($entity),
            "entity" => $entity,
            "type" => DataMonitoringService::TIMELINE,
            "entity_type" => $type
        ]);
    }

    /**
     * @Route("/chart-data-history", name="chart_data_history", options={"expose"=true})
     */
    public function getChartDataHistory(Request $request,
                                        EntityManagerInterface $entityManager,
                                        IOTService $IOTService,
                                        PairingService $pairingService): JsonResponse
    {
        $filters = $request->query->all();
        $query = $request->query;
        $type = $query->get('type');
        $id = $query->get('id');

        $entity = $IOTService->getEntity($entityManager, $type, $id);

        $associatedMessages = $entity->getSensorMessagesBetween(
            $filters["start"],
            $filters["end"],
            Sensor::TEMPERATURE
        );

        $data = $pairingService->buildChartDataFromMessages($associatedMessages);

        return new JsonResponse($data);
    }

    /**
     * @Route("/map-data-history", name="map_data_history", options={"expose"=true})
     */
    public function getMapDataHistory(Request $request,
                                      EntityManagerInterface $entityManager,
                                      IOTService $IOTService): JsonResponse
    {
        $filters = $request->query->all();
        $query = $request->query;

        $type = $query->get('type');
        $id = $query->get('id');

        $entity = $IOTService->getEntity($entityManager, $type, $id);

        $associatedMessages = $entity->getSensorMessagesBetween(
            $filters["start"],
            $filters["end"],
            Sensor::GPS
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

    public function getBreadcrumb($entity) {
        $suffix = ' | Historique des données';
        $breadcrumb = 'IOT | Associations';
        if($entity instanceof Emplacement) {
            $breadcrumb = 'Référentiel | Emplacements';
        } else if($entity instanceof LocationGroup) {
            $breadcrumb = "Référentiel | Groupe d'emplacement";
        } else if($entity instanceof Article) {
            $breadcrumb = 'Stock | Articles';
        } else if($entity instanceof Pack) {
            $breadcrumb = 'Traçabilité | Colis';
        } else if($entity instanceof Preparation) {
            $breadcrumb = 'Ordre | Préparation';
        } else if($entity instanceof OrdreCollecte) {
            $breadcrumb = 'Ordre | Collecte';
        }

        return $breadcrumb . $suffix;
    }

    /**
     * @Route("/{type}/{id}/timeline", name="get_data_history_timeline_api", condition="request.isXmlHttpRequest()")
     */
    public function getPairingTimelineApi(DataMonitoringService $dataMonitoringService,
                                          RouterInterface $router,
                                          EntityManagerInterface $entityManager,
                                          Request $request,
                                          string $type,
                                          string $id): Response {

        $sizeTimelinePage = 6;
        $startTimeline = $request->query->get('start') ?: 0;

        $data = $dataMonitoringService->getTimelineData($entityManager, $router, $type, $id, $startTimeline, $sizeTimelinePage);
        return $this->json($data);
    }
}
