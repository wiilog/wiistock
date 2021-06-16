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

use App\Entity\OrdreCollecte;
use App\Entity\Pack;
use App\Entity\Preparation;

use App\Service\IOT\PairingService;
use DateTimeZone;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use App\Helper\FormatHelper;
use App\Service\DataMonitoringService;
use DateTime;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Annotation\Route;
use function PHPUnit\Framework\isEmpty;

/**
 * @Route("/iot/associations")
 */
class PairingController extends AbstractController {

    /**
     * @Route("/", name="pairing_index", options={"expose"=true})
     * @HasPermission({Menu::IOT, Action::DISPLAY_SENSOR})
     */
    public function index(EntityManagerInterface $entityManager): Response {
        $packs = $entityManager->getRepository(Pack::class)->findWithNoPairing();
        $articles = $entityManager->getRepository(Article::class)->findWithNoPairing();
        $sensorWrappers= $entityManager->getRepository(SensorWrapper::class)->findWithNoActiveAssociation();

        return $this->render("pairing/index.html.twig", [
            'categories' => Sensor::CATEGORIES,
            'sensorTypes' => Sensor::SENSORS,
            "sensorWrappers" => $sensorWrappers,
            'packs' => $packs,
            'articles' => $articles
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
            /** @var Sensor $sensor */
            $sensor = $pairing->getSensorWrapper() ? $pairing->getSensorWrapper()->getSensor() : null;
            $type = $sensor ? $pairing->getSensorWrapper()->getSensor()->getType() : '';

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
                "id" => $pairing->getId(),
                "type" => $type,
                "typeIcon" => Sensor::SENSORS[$type],
                "name" => $pairing->getSensorWrapper() ? $pairing->getSensorWrapper()->getName() : '',
                "element" => $pairing->getEntity() ? $pairing->getEntity()->__toString() : '',
                "elementIcon" => $elementIcon,
                "temperature" => ($sensor && ($sensor->getType() === Sensor::TEMP_TYPE) && $sensor->getLastMessage())
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
            "entities" => [$pairing],
        ]);
    }

    /**
     * @Route("/dissocier/{pairing}", name="unpair", options={"expose"=true})
     * @HasPermission({Menu::IOT, Action::DISPLAY_PAIRING})
     */
    public function unpair(EntityManagerInterface $manager, Pairing $pairing): Response {
        $pairing->setEnd(new DateTime());
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
     * @Route("/creer", name="pairing_new",options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::ORDRE, Action::PAIR_SENSOR}, mode=HasPermission::IN_JSON)
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
            $article = $entityManager->getRepository(Article::class)->find($data['article']);
            $pack = $entityManager->getRepository(Pack::class)->find($data['pack']);

            $typeLocation = explode(':',$data['locations']);
            if($typeLocation[0] == 'location'){
                $location = $entityManager->getRepository(Emplacement::class)->find($typeLocation[1]);
                $locationGroup = null;
            }else {
                $locationGroup = $entityManager->getRepository(LocationGroup::class)->find($typeLocation[1]);
                $location = null;
                $locations = $locationGroup->getLocations();
                foreach ($locations as $locationPairing) {
                    $pairings = $locationPairing->getPairings();
                    foreach ($pairings as $pairing) {
                        if ($pairing->isActive()) {
                            return new JsonResponse([
                                'success' => false,
                                'msg' => 'Ce groupe contient un emplacement ayant déjà une association. Veuillez la supprimer pour continuer'
                            ]);}
                    }
                    $pairingLocation = $pairingService->createPairing($end, $sensorWrapper, $article, $locationPairing, null, $pack);

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
                }
            }
            $pairingLocation = $pairingService->createPairing($end, $sensorWrapper, $article, $location, $locationGroup, $pack);

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

}
