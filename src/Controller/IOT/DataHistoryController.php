<?php

namespace App\Controller\IOT;

use App\Annotation\HasPermission;
use App\Entity\Action;
use App\Entity\Article;
use App\Entity\Emplacement;
use App\Entity\IOT\PairedEntity;
use App\Entity\IOT\Sensor;
use App\Entity\Menu;

use App\Entity\OrdreCollecte;
use App\Entity\Pack;
use App\Entity\Preparation;
use App\Service\IOT\DataMonitoringService;
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
    public function show(Request $request, DataMonitoringService $service): Response {
        $query = $request->query;

        $type = $query->get('type');
        $id = $query->get('id');

        $entity = $this->getEntity($type, $id);

        return $service->render([
            "title" => $this->getBreadcrumb($entity),
            "entity" => $entity,
            "type" => DataMonitoringService::TIMELINE,
            "entity_type" => $type
        ]);
    }

    /**
     * @Route("/chart-data-history", name="chart_data_history", options={"expose"=true})
     */
    public function getChartDataHistory(Request $request, PairingService $pairingService): JsonResponse
    {
        $filters = json_decode($request->getContent(), true);
        $query = $request->query;
        $type = $query->get('type');
        $id = $query->get('id');

        $entity = $this->getEntity($type, $id);

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
    public function getMapDataHistory(Request $request): JsonResponse
    {
        $filters = json_decode($request->getContent(), true);
        $query = $request->query;
        $type = $query->get('type');
        $id = $query->get('id');

        $entity = $this->getEntity($type, $id);

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

    public function getEntity(string $type, int $id): ?PairedEntity {
        $entityManager = $this->getDoctrine()->getManager();

        $entity = null;
        switch ($type) {
            case Sensor::LOCATION:
                $entity = $entityManager->getRepository(Emplacement::class)->find($id);
                break;
            case Sensor::ARTICLE:
                $entity = $entityManager->getRepository(Article::class)->find($id);
                break;
            case Sensor::PACK:
                $entity = $entityManager->getRepository(Pack::class)->find($id);
                break;
            case Sensor::PREPARATION:
                $entity = $entityManager->getRepository(Preparation::class)->find($id);
                break;
            case Sensor::COLLECT:
                $entity = $entityManager->getRepository(OrdreCollecte::class)->find($id);
                break;
        }

        return $entity;
    }

    public function getBreadcrumb($entity) {
        $suffix = ' | Historique des données';
        $breadcrumb = 'IOT | Associations';
        if($entity instanceof Emplacement) {
            $breadcrumb = 'Référentiel | Emplacements';
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

        $entity = $this->getEntity($type, $id);

        $sizeTimelinePage = 6;
        $startTimeline = $request->query->get('start') ?: 0;

        if ($entity) {
            $data = $dataMonitoringService->getTimelineData($entityManager, $router, $entity, $startTimeline, $sizeTimelinePage);
            return $this->json($data);
        }
        else {
            throw new NotFoundHttpException();
        }

        // TODO remove
        return $this->json([
            "data" => [
                [
                    "title" => "Température 1",
                    "subtitle" => "Associé le : 23/03/2021 17:03",
                    "group" => [
                        "title" => "P-202108795875-2",

                    ],
                    "date" => "2021-03-23 17:03",
                    "active" => true
                ],
                [
                    "title" => "GPS3",
                    "subtitle" => "Dissocié le : 23/03/2021 16:03",
                    "group" => [
                        "title" => "P-202108795875-2",
                    ],
                    "date" => "2021-03-23 16:03",
                    "active" => false
                ],
                [
                    "title" => "GPS3",
                    "subtitle" => "Associé le : 23/03/2021 15:03",
                    "group" => [
                        "title" => "P-202108795875-2",
                    ],
                    "date" => "2021-03-23 15:03",
                    "active" => false
                ],
                [
                    "title" => "GPS3",
                    "subtitle" => "Dissocié le : 23/03/2021 12:03",
                    "group" => [
                        "title" => "L-20210879165-01"
                    ],
                    "date" => "2021-03-23 12:03",
                    "active" => false
                ],
                [
                    "title" => "GPS3",
                    "subtitle" => "Associé le : 23/03/2021 11:03",
                    "group" => [
                        "title" => "P-202106565265-01",
                    ],
                    "date" => "2021-03-23 11:03",
                    "active" => false
                ]
            ],
            "isEnd" => false
        ]);
    }
}
