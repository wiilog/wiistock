<?php

namespace App\Controller\IOT;

use App\Annotation\HasPermission;
use App\Entity\Action;
use App\Entity\Article;
use App\Entity\Emplacement;
use App\Entity\IOT\Pairing;
use App\Entity\IOT\Sensor;
use App\Entity\IOT\SensorMessage;
use App\Entity\IOT\SensorWrapper;
use App\Entity\LocationGroup;
use App\Entity\Menu;

use App\Entity\Pack;

use App\Service\IOT\IOTService;
use App\Service\IOT\PairingService;
use DateTimeZone;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use App\Helper\FormatHelper;
use App\Service\IOT\DataMonitoringService;
use DateTime;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Throwable;
use WiiCommon\Helper\Stream;

/**
 * @Route("/iot/associations")
 */
class PairingController extends AbstractController {

    /**
     * @Route("/", name="pairing_index", options={"expose"=true})
     * @HasPermission({Menu::IOT, Action::DISPLAY_SENSOR})
     */
    public function index(EntityManagerInterface $entityManager): Response {
        $sensorWrappers= $entityManager->getRepository(SensorWrapper::class)->findWithNoActiveAssociation();

        return $this->render("pairing/index.html.twig", [
            'categories' => Sensor::CATEGORIES,
            'sensorTypes' => Sensor::SENSOR_ICONS,
            "sensorWrappers" => $sensorWrappers,
        ]);
    }

    /**
     * @Route("/api", name="pairing_api", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::IOT, Action::DISPLAY_SENSOR}, mode=HasPermission::IN_JSON)
     */
    public function api(Request $request,
                        IOTService $IOTService,
                        EntityManagerInterface $manager): Response {
        $pairingRepository = $manager->getRepository(Pairing::class);
        $filters = $request->query;

        $queryResult = $pairingRepository->findByParamsAndFilters($filters);
        $pairings = $queryResult["data"];
        $rows = [];
        /** @var Pairing $pairing */
        foreach ($pairings as $pairing) {
            /** @var Sensor $sensor */
            $sensor = $pairing->getSensorWrapper() ? $pairing->getSensorWrapper()->getSensor() : null;
            $type = $sensor ? FormatHelper::type($sensor->getType()) : '';

            $elementIcon = $IOTService->getEntityCodeFromEntity($pairing->getEntity()) ?? '';

            $rows[] = [
                "id" => $pairing->getId(),
                "type" => $type,
                "typeIcon" => Sensor::SENSOR_ICONS[$type],
                "name" => $pairing->getSensorWrapper() ? $pairing->getSensorWrapper()->getName() : '',
                "element" => $pairing->getEntity() ? $pairing->getEntity()->__toString() : '',
                "elementIcon" => $elementIcon,
                "temperature" => ($sensor && (FormatHelper::type($sensor->getType()) === Sensor::TEMPERATURE) && $sensor->getLastMessage())
                    ? $sensor->getLastMessage()->getContent()
                    : '',
                "lowTemperatureThreshold" => SensorMessage::LOW_TEMPERATURE_THRESHOLD,
                "highTemperatureThreshold" => SensorMessage::HIGH_TEMPERATURE_THRESHOLD,
            ];
        }

        return $this->json($rows);
    }

    /**
     * @Route("/voir/{pairing}", name="pairing_show", options={"expose"=true})
     * @HasPermission({Menu::IOT, Action::DISPLAY_PAIRING})
     */
    public function show(DataMonitoringService $service, Pairing $pairing): Response {
        return $service->render([
            "title" => "IOT | Associations | Détails",
            "type" => DataMonitoringService::PAIRING,
            "entity" => $pairing,
        ]);
    }

    /**
     * @Route("/dissocier/{pairing}", name="unpair", options={"expose"=true})
     * @HasPermission({Menu::IOT, Action::DISPLAY_PAIRING})
     */
    public function unpair(EntityManagerInterface $manager, Pairing $pairing): Response {
        $pairing->setEnd(new DateTime('now', new DateTimeZone('Europe/Paris')));
        $pairing->setActive(false);
        $manager->flush();

        return $this->json([
            "success" =>  true,
            "selector" => ".pairing-end-date-{$pairing->getId()}",
            "date" => FormatHelper::datetime($pairing->getEnd()),
        ]);
    }

    /**
     * @Route("/modifier-fin", name="pairing_edit_end", options={"expose"=true})
     * @HasPermission({Menu::IOT, Action::DISPLAY_PAIRING})
     */
    public function modifyEnd(Request $request, EntityManagerInterface $manager): Response {
        if($data = json_decode($request->getContent(), true)) {
            $pairing = $manager->find(Pairing::class, $data["id"]);

            $end = new DateTime($data["end"], new DateTimeZone("Europe/Paris"));
            if($end < new DateTime("now", new DateTimeZone("Europe/Paris"))) {
                return $this->json([
                    "success" => false,
                    "msg" => "La date de fin doit être supérieure à la date actuelle",
                ]);
            }

            $pairing->setEnd($end);
            $manager->flush();

            return $this->json([
                "success" => true,
                "selector" => ".pairing-end-date-{$pairing->getId()}",
                "date" => FormatHelper::datetime($pairing->getEnd()),
            ]);
        }

        throw new BadRequestHttpException();
    }

    /**
     * @Route("/creer", name="pairing_new", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::IOT, Action::CREATE}, mode=HasPermission::IN_JSON)
     */
    public function new(PairingService $pairingService,
                        EntityManagerInterface $entityManager,
                        Request $request): Response
    {
        if($data = json_decode($request->getContent(), true)) {
            if(!$data['sensor'] && !$data['sensorCode']) {
                return $this->json([
                    'success' => false,
                    'msg' => 'Un capteur/code capteur est obligatoire pour valider l\'association'
                ]);
            }

            $end = new DateTime($data['date-pairing']);
            $sensorWrapper = $entityManager->getRepository(SensorWrapper::class)->findByNameOrCode($data['sensor'], $data['sensorCode']);

            if(isset($data['article'])) {
                $article = $entityManager->getRepository(Article::class)->find($data['article']);
            } else if(isset($data['pack'])) {
                $pack = $entityManager->getRepository(Pack::class)->find($data['pack']);
            } else {
                $typeLocation = explode(':', $data['locations']);
                if ($typeLocation[0] == 'location') {
                    $location = $entityManager->getRepository(Emplacement::class)->find($typeLocation[1]);
                } else {
                    $locationGroup = $entityManager->getRepository(LocationGroup::class)->find($typeLocation[1]);
                }
            }

            $pairingLocation = $pairingService->createPairing($end, $sensorWrapper, $article ?? null, $location ?? null, $locationGroup ?? null, $pack ?? null);
            $entityManager->persist($pairingLocation);

            try {
                $entityManager->flush();
            } /** @noinspection PhpRedundantCatchClauseInspection */
            catch (UniqueConstraintViolationException $e) {
                return new JsonResponse([
                    'success' => false,
                    'msg' => 'Une autre association est en cours de création, veuillez réessayer.'
                ]);
            }

            $number = $sensorWrapper->getName();
            return $this->json([
                'success' => true,
                'msg' => "L'assocation avec le capteur <strong>${number}</strong> a bien été créée"
            ]);
        }

        throw new BadRequestHttpException();
    }

    /**
     * @Route("/map-data/{pairing}", name="pairing_map_data", condition="request.isXmlHttpRequest()")
     */
    public function getMapData(Request $request, Pairing $pairing): JsonResponse
    {
        $filters = $request->query->all();
        $associatedMessages = $pairing->getSensorMessagesBetween(
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

    /**
     * @Route("/chart-data/{pairing}", name="pairing_chart_data", condition="request.isXmlHttpRequest()", options={"expose"=true}, methods="GET|POST")
     */
    public function getChartData(Request $request, Pairing $pairing): JsonResponse
    {
        $filters = $request->query->all();
        $associatedMessages = $pairing->getSensorMessagesBetween(
            $filters["start"],
            $filters["end"],
            Sensor::TEMPERATURE
        );

        $data = ["colors" => []];
        foreach ($associatedMessages as $message) {
            $date = $message->getDate();
            $sensor = $message->getSensor();

            if(!isset($data['colors'][$sensor->getCode()])) {
                srand($sensor->getId());
                $data['colors'][$sensor->getCode()] = sprintf('#%06X', mt_rand(0, 0xFFFFFF));
            }

            $dateStr = $date->format('Y-m-d H:i:s');
            $sensorCode = $sensor->getCode();
            if (!isset($data[$dateStr])) {
                $data[$dateStr] = [];
            }
            $data[$dateStr][$sensorCode] = floatval($message->getContent());
        }

        srand();

        return new JsonResponse($data);
    }

}
