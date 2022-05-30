<?php

namespace App\Controller\Transport;

use App\Annotation\HasPermission;
use App\Entity\Action;
use App\Entity\CategorieStatut;
use App\Entity\FiltreSup;
use App\Entity\IOT\SensorMessage;
use App\Entity\Menu;
use App\Entity\Setting;
use App\Entity\Statut;
use App\Entity\Transport\TransportCollectRequest;
use App\Entity\Transport\TransportDeliveryRequest;
use App\Entity\Transport\TransportOrder;
use App\Entity\Transport\TransportRound;
use App\Entity\Transport\TransportRoundLine;
use App\Entity\Utilisateur;
use App\Exceptions\FormException;
use App\Exceptions\GeoException;
use App\Helper\FormatHelper;
use App\Service\GeoService;
use App\Service\IOT\IOTService;
use App\Service\StatusHistoryService;
use App\Service\Transport\TransportHistoryService;
use App\Service\UniqueNumberService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;
use WiiCommon\Helper\Stream;


#[Route("transport/tournee")]
class RoundController extends AbstractController {

    #[Route("/liste", name: "transport_round_index", methods: "GET")]
    public function index(EntityManagerInterface $em): Response {
        $statusRepository = $em->getRepository(Statut::class);
        $statuses = $statusRepository->findByCategorieName(CategorieStatut::TRANSPORT_ROUND);

        return $this->render('transport/round/index.html.twig', [
            'statuts' => $statuses,
        ]);
    }

    #[Route('/api', name: 'transport_round_api', options: ['expose' => true], methods: 'POST', condition: 'request.isXmlHttpRequest()')]
    #[HasPermission([Menu::DEM, Action::DISPLAY_TRANSPORT], mode: HasPermission::IN_JSON)]
    public function api(Request $request, EntityManagerInterface $manager): Response {
        $filterSupRepository = $manager->getRepository(FiltreSup::class);
        $roundRepository = $manager->getRepository(TransportRound::class);

        $filters = $filterSupRepository->getFieldAndValueByPageAndUser(FiltreSup::PAGE_TRANSPORT_ROUNDS, $this->getUser());
        $queryResult = $roundRepository->findByParamAndFilters($request->request, $filters);

        $transportRounds = [];
        foreach ($queryResult["data"] as $transportRound) {
            $expectedAtStr = $transportRound->getExpectedAt()?->format("dmY");
            if ($expectedAtStr) {
                $transportRounds[$expectedAtStr][] = $transportRound;
            }
        }

        $rows = [];
        $currentRow = [];
        foreach ($transportRounds as $date => $rounds) {
            $date = DateTime::createFromFormat("dmY", $date);
            $date = FormatHelper::longDate($date);

            $row = "<div class='transport-list-date px-1 pb-2 pt-3'>$date</div>";
            if(!$rows) {
                $export = "<span>
                    <button type='button' class='btn btn-primary mr-1'
                            onclick='saveExportFile(`transport_rounds_export`)'>
                        <i class='fa fa-file-csv mr-2' style='padding: 0 2px'></i>
                        Exporter au format CSV
                    </button>
                </span>";

                $row = "<div class='d-flex flex-column-reverse flex-md-row justify-content-between'>$row $export</div>";
            }

            $rows[] = [
                "content" => $row,
            ];

            /** @var TransportRound $transportRound */
            foreach ($rounds as $transportRound) {
                $hours = null;
                $minutes = null;
                if ($transportRound->getBeganAt() && $transportRound->getEndedAt()) {
                    $timestamp = $transportRound->getEndedAt()->getTimestamp() - $transportRound->getBeganAt()->getTimestamp();
                    $hours = floor(($timestamp / 60) / 60);
                    $minutes = floor($timestamp / 60) - ($hours * 60);
                }

                $hasRejectedPacks = Stream::from($transportRound->getTransportRoundLines())
                    ->some(fn(TransportRoundLine $line) => $line->getOrder()->hasRejectedPacks());

                $hasRejectedDeliveries = Stream::from($transportRound->getTransportRoundLines())
                    ->some(fn(TransportRoundLine $line) => $line->getOrder()->isRejected());

                $currentRow[] = $this->renderView("transport/round/list_card.html.twig", [
                    "hasRejectedPacks" => $hasRejectedPacks,
                    "hasRejectedDeliveries" => $hasRejectedDeliveries,
                    "prefix" => TransportRound::NUMBER_PREFIX,
                    "round" => $transportRound,
                    "realTime" => isset($hours) && isset($minutes)
                        ? ($hours . "h" . $minutes . "min")
                        : '-',
                ]);
            }

            if ($currentRow) {
                $row = "<div class='transport-row row no-gutters'>" . join($currentRow) . "</div>";
                $rows[] = [
                    "content" => $row,
                ];

                $currentRow = [];
            }
        }

        return $this->json([
            "data" => $rows,
            "recordsTotal" => $queryResult["total"],
            "recordsFiltered" => $queryResult["count"],
        ]);
    }

    #[Route("/voir/{transportRound}", name: "transport_round_show", methods: "GET")]
    public function show(TransportRound $transportRound,
                         EntityManagerInterface $entityManager,
                         RouterInterface $router,
    ): Response {
        $realTime = null;
        if ( $transportRound->getBeganAt() != null & $transportRound->getEndedAt() != null  ) {
            $realTimeDif = $transportRound->getEndedAt()->diff($transportRound->getBeganAt());
            $realTimeJ = $realTimeDif->format("%a");
            $realTime = $realTimeDif->format("%h") + ($realTimeJ * 24) . "h" . $realTimeDif->format(" %i") . "min";
        };

        $calculationsPoints = $transportRound->getCoordinates();
        $calculationsPoints['startPoint']['name'] = TransportRound::NAME_START_POINT;
        $calculationsPoints['startPointScheduleCalculation']['name'] = TransportRound::NAME_START_POINT_SCHEDULE_CALCULATION;
        $calculationsPoints['endPoint']['name'] = TransportRound::NAME_END_POINT;
        $transportPoints = Stream::from($transportRound->getTransportRoundLines())->map(function (TransportRoundLine $line) {
            if (!$line->getOrder()->isRejected()) {
                $contact = $line->getOrder()->getRequest()->getContact();
                return [
                    'priority' => $line->getPriority(),
                    'longitude' => $contact->getAddressLongitude(),
                    'latitude' => $contact->getAddressLatitude(),
                    'name' => $contact->getName(),
                ];
            }
        })->toArray();

        $delivererPosition = $transportRound->getBeganAt()
            ? $transportRound->getDeliverer()->getVehicle()->getLastPosition( $transportRound->getBeganAt(), $transportRound->getEndedAt())
            : null;

        $urls = [];
        $transportDateBeganAt = $transportRound->getBeganAt();

        $now = new DateTime();
        foreach ($transportRound->getDeliverer()?->getVehicle()?->getLocations() as $location) {
            if ($location->getActivePairing()) {
                $urls = [
                    "fetch_url" => $router->generate("chart_data_history", [
                        "type" => IOTService::getEntityCodeFromEntity($location),
                        "id" => $location->getId(),
                        'start' => $transportRound->getCreatedAt()->format('Y-m-d\TH:i'),
                        'end' => $transportRound->getEndedAt() ?? $now->format('Y-m-d\TH:i'),
                    ], UrlGeneratorInterface::ABSOLUTE_URL)
                ];
            }
        }
        //TODO WIIS-7229 appliquer les nouvelles bornes
        return $this->render('transport/round/show.html.twig', [
            "transportRound" => $transportRound,
            "realTime" => $realTime,
            "calculationsPoints" => $calculationsPoints,
            "transportPoints" => $transportPoints,
            "delivererPosition" => $delivererPosition,
            "urls" => $urls,
            "roundDateBegan" => $transportDateBeganAt,
            "minTemp" => SensorMessage::LOW_TEMPERATURE_THRESHOLD,
            "maxTemp" => SensorMessage::HIGH_TEMPERATURE_THRESHOLD,
        ]);
    }

    #[Route("/planifier", name: "transport_round_plan", options: ['expose' => true], methods: "GET")]
    #[HasPermission([Menu::ORDRE, Action::SCHEDULE_TRANSPORT_ROUND])]
    public function plan(Request $request,
                         EntityManagerInterface $entityManager,
                         UniqueNumberService $uniqueNumberService): Response {
        if ($request->query->get('dateRound')) {
            $round = new TransportRound();

            $expectedAt = DateTime::createFromFormat('Y-m-d',  $request->query->get('dateRound'));
            $number = $uniqueNumberService->create(
                $entityManager,
                null,
                TransportRound::class,
                UniqueNumberService::DATE_COUNTER_FORMAT_TRANSPORT,
                $expectedAt
            );

            $round
                ->setExpectedAt($expectedAt)
                ->setNumber($number);
        }
        else if( $request->query->get('transportRound')){
            $round = $entityManager->getRepository(TransportRound::class)->findOneBy(['id' => $request->query->get('transportRound')]);

            if (!$round || $round->getStatus()?->getCode() !== TransportRound::STATUS_AWAITING_DELIVERER) {
                throw new NotFoundHttpException('Impossible de planifier cette tournée');
            }
        }
        else{
            throw new NotFoundHttpException('Impossible de planifier une tournée');
        }

        $transportOrders = $entityManager->getRepository(TransportOrder::class)->findToAssignByDate($round->getExpectedAt());
        $transportOrders = Stream::from($transportOrders)
            ->sort(function (TransportOrder $a, TransportOrder $b) {
                $getOrderTimestamp = function (TransportOrder $order) {
                    $request = $order->getRequest();
                    $dateTime = $request instanceof TransportCollectRequest
                        ? DateTime::createFromFormat('Y-m-d H:i', $request->getValidatedDate()->format('Y-m-d') . ' ' . $request->getTimeslot()->getEnd())
                        : $request->getExpectedAt();
                    return $dateTime->getTimestamp();
                };
                return $getOrderTimestamp($a) <=> $getOrderTimestamp($b);
            })
            ->toArray();

        $contactDataByOrderId = Stream::from(
            $transportOrders,
            Stream::from($round->getTransportRoundLines())->map(fn(TransportRoundLine $transportRoundLine) => $transportRoundLine->getOrder())
        )
            ->keymap(function(TransportOrder  $transportOrder){
                $request = $transportOrder->getRequest();
                return [
                    $transportOrder->getId(),
                    [
                        'latitude' => $request->getContact()->getAddressLatitude(),
                        'longitude' => $request->getContact()->getAddressLongitude(),
                        'contact' => $request->getContact()->getName(),
                        'time' => $request instanceof TransportCollectRequest
                            ? ( $request->getTimeSlot()?->getName() ?: $request->getExpectedAt()->format('H:i') )
                            : $request->getExpectedAt()->format('H:i'),
                    ]
                ];
            })
            ->toArray();

        return $this->render('transport/round/plan.html.twig', [
            'round' => $round,
            'prefixNumber' => TransportRound::NUMBER_PREFIX,
            'transportOrders' => $transportOrders,
            'contactData' => $contactDataByOrderId,
        ]);
    }

    #[Route("/save", name: "transport_round_save", options: ['expose' => true], methods: "POST", condition: 'request.isXmlHttpRequest()')]
    public function save(Request                 $request,
                         EntityManagerInterface  $entityManager,
                         StatusHistoryService    $statusHistoryService,
                         TransportHistoryService $transportHistoryService,
                         UniqueNumberService     $uniqueNumberService): JsonResponse {

        $transportRoundRepository = $entityManager->getRepository(TransportRound::class);
        $userRepository = $entityManager->getRepository(Utilisateur::class);
        $transportOrderRepository = $entityManager->getRepository(TransportOrder::class);
        $statusRepository = $entityManager->getRepository(Statut::class);

        $number = $request->request->get('number');
        $expectedAtDate = $request->request->get('expectedAtDate');
        $expectedAtTime = $request->request->get('expectedAtTime');
        $startPoint = $request->request->get('startPoint');
        $startPointScheduleCalculation = $request->request->get('startPointScheduleCalculation');
        $endPoint = $request->request->get('endPoint');
        $deliverer = $request->request->get('deliverer');
        $transportRoundId = $request->request->get('transportRoundId');
        $coordinates = json_decode($request->request->get('coordinates'), true) ?: [];
        $affectedOrderIds = Stream::explode(',', $request->request->get('affectedOrders'))->toArray();

        $expectedAt = FormatHelper::parseDatetime("$expectedAtDate $expectedAtTime");
        if (!$expectedAt) {
            throw new FormException('Format de la date attendue invalide');
        }

        if ($transportRoundId) {
            $transportRound = $transportRoundRepository->find($transportRoundId);

            if ($transportRound->getStatus()?->getCode() !== TransportRound::STATUS_AWAITING_DELIVERER) {
                throw new FormException('Impossible de planifier cette tournée');
            }
        }
        else {
            $transportRound = $transportRoundRepository->findOneBy(['number' => $number]);
            if ($transportRound) {
                $exception = new FormException('Une tournée avec le même numéro a été créée en même temps. Le code a été actualisé, veuillez enregistrer de nouveau.');
                $exception->setData([
                    'newNumber' => $uniqueNumberService->create(
                        $entityManager,
                        null,
                        TransportRound::class,
                        UniqueNumberService::DATE_COUNTER_FORMAT_TRANSPORT
                    ),
                ]);
                throw $exception;
            }

            $roundStatus = $statusRepository
                ->findOneByCategorieNameAndStatutCode(CategorieStatut::TRANSPORT_ROUND, TransportRound::STATUS_AWAITING_DELIVERER);

            $transportRound = new TransportRound();
            $transportRound
                ->setCreatedAt(new DateTime())
                ->setNumber($number)
                ->setCreatedBy($this->getUser());

            $statusHistoryService->updateStatus($entityManager, $transportRound, $roundStatus);

            $entityManager->persist($transportRound);
        }

        $deliverer = $userRepository->find($deliverer);

//        TODO set estimated ?
        $transportRound
            ->setExpectedAt($expectedAt)
            ->setDeliverer($deliverer)
            ->setStartPoint($startPoint)
            ->setStartPointScheduleCalculation($startPointScheduleCalculation)
            ->setEndPoint($endPoint)
            ->setCoordinates($coordinates);

        if (empty($affectedOrderIds)) {
            throw new FormException("Il n'y a aucun ordre dans la tournée, veuillez réessayer");
        }

        $collectOrderAssignStatus = $statusRepository
            ->findOneByCategorieNameAndStatutCode(CategorieStatut::TRANSPORT_ORDER_COLLECT, TransportOrder::STATUS_ASSIGNED);

        $deliveryOrderAssignStatus = $statusRepository
            ->findOneByCategorieNameAndStatutCode(CategorieStatut::TRANSPORT_ORDER_DELIVERY, TransportOrder::STATUS_ASSIGNED);

        /** @var TransportOrder $order */
        foreach ($affectedOrderIds as $index => $orderId) {
            $order = $transportOrderRepository->find($orderId);
            if ($order) {
                $affectationAllowed = (
                    $order->getTransportRoundLines()->isEmpty()
                    || Stream::from ($order->getTransportRoundLines())
                        ->map(fn(TransportRoundLine $line) => $line->getTransportRound())
                        ->every(fn(TransportRound $round) => $round->getStatus()?->getCode() === TransportRound::STATUS_FINISHED)
                );
                $priority = $index + 1;
                if (!$affectationAllowed) {
                    throw new FormException("L'ordre n°{$priority} est déjà affecté à une tournée non terminée");
                }

                $line = $transportRound->getTransportRoundLine($order);
                if (!isset($line)) {
                    $line = new TransportRoundLine();
                    // TODO            $line->setEstimatedAt() ??
                    $line->setOrder($order);
                    $entityManager->persist($line);
                    $transportRound->addTransportRoundLine($line);

                    // set order status + add status history + add transport history
                    $status = $order->getRequest() instanceof TransportDeliveryRequest ? $deliveryOrderAssignStatus : $collectOrderAssignStatus;

                    $statusHistory = $statusHistoryService->updateStatus($entityManager, $order, $status);

                    $transportHistoryService->persistTransportHistory($entityManager, [$order->getRequest(), $order], TransportHistoryService::TYPE_AFFECTED_ROUND, [
                        'user' => $this->getUser(),
                        'deliverer' => $transportRound->getDeliverer(),
                        'round' => $transportRound,
                        'history' => $statusHistory,
                    ]);
                }

                $line
                    ->setPriority($priority);
            }
            else {
                throw new FormException("Un ordre affecté n'existe plus, veuillez réessayer");
            }
        }

        if ($transportRound->getTransportRoundLines()->isEmpty()) {
            throw new FormException("Il n'y a aucun ordre dans la tournée, veuillez réessayer");
        }

        $entityManager->flush();

        return $this->json([
            'success' => true,
            'msg' => 'La tournée ' . TransportRound::NUMBER_PREFIX . $transportRound->getNumber() . ' a été planifiée avec succes',
            'redirect' => $this->generateUrl('transport_round_show', [
                'transportRound' => $transportRound->getId(),
            ]),
       ]);
    }

    #[Route("/api-get-address-coordinates", name: "transport_round_address_coordinates_get", options: ['expose' => true], methods: "GET")]
    public function getAddressCoordinates(Request $request, GeoService $geoService): Response
    {
        try {
            [$lat, $lon] = $geoService->fetchCoordinates($request->query->get('address'));
        }
        catch (GeoException $exception) {
            throw new FormException($exception->getMessage());
        }

        return $this->json([
            'success' => true,
            'latitude' => $lat,
            'longitude' => $lon,
        ]);
    }
}
