<?php

namespace App\Controller\IOT;

use App\Annotation\HasPermission;
use App\Entity\Action;
use App\Entity\Article;
use App\Entity\Collecte;
use App\Entity\DeliveryRequest\Demande;
use App\Entity\Emplacement;
use App\Entity\IOT\Sensor;
use App\Entity\LocationGroup;
use App\Entity\Menu;

use App\Entity\OrdreCollecte;
use App\Entity\Pack;
use App\Entity\PreparationOrder\Preparation;
use App\Service\IOT\DataMonitoringService;
use App\Service\IOT\PairingService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use WiiCommon\Helper\Stream;
use DateTime;

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
                         DataMonitoringService $dataMonitoringService,
                         TranslatorInterface $trans): Response {
        $query = $request->query;

        $type = $query->get('type');
        $id = $query->get('id');

        $entity = $dataMonitoringService->getEntity($entityManager, $type, $id);
        $end = new DateTime('now');
        $end->modify('last day of this month');
        $end->setTime(23, 59, 59);
        $start = clone $end;
        $start->setTime(0, 0, 0);
        $start->modify('first day of this month');
        return $dataMonitoringService->render([
            "breadcrumb" => $this->getBreadcrumb($trans, $entity),
            "entity" => $entity,
            "type" => DataMonitoringService::TIMELINE,
            "entity_type" => $type,
            'startFilter' => $start,
            "endFilter" => $end
        ]);
    }

    /**
     * @Route("/chart-data-history", name="chart_data_history", options={"expose"=true})
     */
    public function getChartDataHistory(Request $request,
                                        EntityManagerInterface $entityManager,
                                        DataMonitoringService $dataMonitoringService,
                                        PairingService $pairingService): JsonResponse
    {
        $filters = $request->query->all();
        $query = $request->query;
        $type = $query->get('type');
        $id = $query->get('id');
        $entity = $dataMonitoringService->getEntity($entityManager, $type, $id);
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
                                      DataMonitoringService $dataMonitoringService): JsonResponse
    {
        $filters = $request->query->all();
        $query = $request->query;

        $type = $query->get('type');
        $id = $query->get('id');

        $entity = $dataMonitoringService->getEntity($entityManager, $type, $id);
        $associatedMessages = $entity->getSensorMessagesBetween(
            $filters["start"],
            $filters["end"],
            Sensor::GPS
        );

        $data = [];
        $lastCoordinates = null;
        $lastDate = null;
        foreach ($associatedMessages as $message) {
            $date = $message->getDate();
            $sensor = $message->getSensor();

            $dateStr = $date->format('d/m/Y H:i:s');
            $wrapper = $sensor->getAvailableSensorWrapper();
            $sensorCode = ($wrapper ? $wrapper->getName() . ' : ' : '') . $sensor->getCode();
            if (!isset($data[$sensorCode])) {
                $data[$sensorCode] = [];
            }
            $coordinates = Stream::explode(',', $message->getContent())
                ->map(fn($coordinate) => floatval($coordinate))
                ->toArray();
            if ($coordinates[0] !== -1.0 || $coordinates[1] !== -1.0) {
                if ($lastCoordinates) {
                    $distanceBetweenLastPoint = $dataMonitoringService->vincentyGreatCircleDistance($lastCoordinates[0], $lastCoordinates[1], $coordinates[0], $coordinates[1]);
                    $interval = $lastDate->diff($message->getDate());
                    if ($distanceBetweenLastPoint > 200.0 || (($interval->days * 24) + $interval->h) > 23) {
                        $data[$sensorCode][$dateStr] = $coordinates;
                        $lastCoordinates = $coordinates;
                        $lastDate = $message->getDate();
                    }
                } else {
                    $data[$sensorCode][$dateStr] = $coordinates;
                    $lastCoordinates = $coordinates;
                    $lastDate = $message->getDate();
                }
            }
        }
        return new JsonResponse($data);
    }

    public function getBreadcrumb(TranslatorInterface $trans, $entity) {

        $suffix = ' | Historique des données';
        $title = $trans->trans("IoT.IoT") . ' | Associations';
        $path = null;
        if($entity instanceof Emplacement) {
            $title = 'Référentiel | Emplacements';
            $path = 'emplacement_index';
        } else if($entity instanceof LocationGroup) {
            $title = "Référentiel | Groupe d'emplacements";
            $path = 'emplacement_index';
        } else if($entity instanceof Article) {
            $title = 'Stock | Articles';
            $path = 'article_index';
        } else if($entity instanceof Pack) {
            $title = 'Traçabilité | Colis';
            $path = 'pack_index';
        } else if($entity instanceof Preparation || $entity instanceof Demande) {
            $title = 'Demande | Livraison';
            $path = 'demande_index';
        } else if($entity instanceof OrdreCollecte || $entity instanceof Collecte) {
            $title = 'Demande | Collecte';
            $path = 'collecte_index';
        }

        return [
            'title' => $title . $suffix,
            'path' => $path
        ];
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
