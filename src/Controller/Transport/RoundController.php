<?php

namespace App\Controller\Transport;

use App\Annotation\HasPermission;
use App\Controller\AbstractController;
use App\Entity\Action;
use App\Entity\CategorieStatut;
use App\Entity\FiltreSup;
use App\Entity\IOT\Sensor;
use App\Entity\IOT\TriggerAction;
use App\Entity\Menu;
use App\Entity\Setting;
use App\Entity\Statut;
use App\Entity\Transport\TransportCollectRequest;
use App\Entity\Transport\TransportDeliveryRequest;
use App\Entity\Transport\TransportOrder;
use App\Entity\Transport\TransportRequest;
use App\Entity\Transport\TransportRound;
use App\Entity\Transport\TransportRoundLine;
use App\Entity\Transport\Vehicle;
use App\Entity\Utilisateur;
use App\Exceptions\FormException;
use App\Exceptions\GeoException;
use App\Helper\FormatHelper;
use App\Service\CSVExportService;
use App\Service\GeoService;
use App\Service\IOT\IOTService;
use App\Service\NotificationService;
use App\Service\OperationHistoryService;
use App\Service\PDFGeneratorService;
use App\Service\SettingsService;
use App\Service\StatusHistoryService;
use App\Service\Transport\TransportRoundService;
use App\Service\UniqueNumberService;
use App\Service\UserService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Bundle\SnappyBundle\Snappy\Response\PdfResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;
use WiiCommon\Helper\Stream;


#[Route("transport/tournee")]
class RoundController extends AbstractController {

    #[Route("/liste", name: "transport_round_index", methods: "GET")]
    public function index(EntityManagerInterface $em): Response {
        $statusRepository = $em->getRepository(Statut::class);
        $roundRepository = $em->getRepository(TransportRound::class);
        $statuses = $statusRepository->findByCategorieName(CategorieStatut::TRANSPORT_ROUND);
        $roundCategorie = $em->getRepository(CategorieStatut::class)->findOneBy(['nom' => CategorieStatut::TRANSPORT_ROUND])->getId();
        $ongoingStatus = $statusRepository->findOneBy(['code' => TransportRound::STATUS_ONGOING , 'categorie' => $roundCategorie ])?->getId();
        $deliverersPositions = Stream::from($roundRepository->findBy(['status' => $ongoingStatus]))
            ->filterMap(fn(TransportRound $round) => $em->getRepository(Vehicle::class)->findOneByDateLastMessageBetween(
                $round->getVehicle(),
                $round->getBeganAt(),
                new DateTime('now'),
                Sensor::GPS))
            ->map(fn(Array $position) => $position['content'])
            ->flatten()
            ->toArray();

        return $this->render('transport/round/index.html.twig', [
            'deliverersPositions' => $deliverersPositions,
            'statuts' => $statuses,
        ]);
    }

    #[Route('/api', name: 'transport_round_api', options: ['expose' => true], methods: 'POST', condition: 'request.isXmlHttpRequest()')]
    #[HasPermission([Menu::DEM, Action::DISPLAY_TRANSPORT], mode: HasPermission::IN_JSON)]
    public function api(Request $request, EntityManagerInterface $manager): Response {
        $filterSupRepository = $manager->getRepository(FiltreSup::class);
        $roundRepository = $manager->getRepository(TransportRound::class);
        $user = $this->getUser();

        $filters = $filterSupRepository->getFieldAndValueByPageAndUser(FiltreSup::PAGE_TRANSPORT_ROUNDS, $user);
        $queryResult = $roundRepository->findByParamAndFilters($request->request, $filters);

        $orderedTransportRounds = Stream::from($queryResult["data"])
            ->sort(fn(TransportRound $r1, TransportRound $r2) => $r2->getId() <=> $r1->getId())
            ->toArray();
        $transportRounds = [];
        foreach ($orderedTransportRounds as $transportRound) {
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

                $currentRow[] = $this->renderView("transport/round/list_card.html.twig", [
                    "hasRejectedPacks" => $hasRejectedPacks,
                    "prefix" => TransportRound::NUMBER_PREFIX,
                    "hasExceededThreshold" => $transportRound->isThresholdExceeded(),
                    "round" => $transportRound,
                    "realTime" => isset($hours) && isset($minutes)
                        ? (($hours < 10 ? "0$hours" : $hours) . "h" . ($minutes < 10 ? "0$minutes" : $minutes) . "min")
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
    public function show(TransportRound         $transportRound,
                         RouterInterface        $router,
                         EntityManagerInterface $entityManager): Response {
        $realTime = null;
        if ($transportRound->getBeganAt() != null && $transportRound->getEndedAt() != null) {
            $realTimeDif = $transportRound->getEndedAt()->diff($transportRound->getBeganAt());
            $realTimeJ = $realTimeDif->format("%a");
            $realTime = ($realTimeDif->format("%h") + ($realTimeJ * 24)) . "h" . $realTimeDif->format(" %i") . "min";
        }

        $calculationsPoints = $transportRound->getCoordinates();
        $calculationsPoints['startPoint']['name'] = TransportRound::NAME_START_POINT;
        $calculationsPoints['startPointScheduleCalculation']['name'] = TransportRound::NAME_START_POINT_SCHEDULE_CALCULATION;
        $calculationsPoints['endPoint']['name'] = TransportRound::NAME_END_POINT;

        $transportPoints = Stream::from($transportRound->getTransportRoundLines())
            ->filterMap(function (TransportRoundLine $line) {
                $contact = $line->getOrder()->getRequest()->getContact();
                return [
                    'priority' => $line->getPriority(),
                    'longitude' => $contact->getAddressLongitude(),
                    'latitude' => $contact->getAddressLatitude(),
                    'name' => $contact->getName(),
                ];
            })
            ->toArray();

        $delivererPosition = $transportRound->getBeganAt()
            ? $entityManager->getRepository(Vehicle::class)->findOneByDateLastMessageBetween(
                $transportRound->getVehicle(),
                $transportRound->getBeganAt(),
                $transportRound->getEndedAt(),
                Sensor::GPS)
            : null;
        $delivererPosition = $delivererPosition ? $delivererPosition["content"] : null;

        $urls = [];
        $transportDateBeganAt = $transportRound->getBeganAt();
        $locations = $transportRound->getLocations();

        if ($transportDateBeganAt) {
            foreach ($locations as $location) {
                $triggerActions = $location->getActivePairing()?->getSensorWrapper()?->getTriggerActions();
                if ($triggerActions) {
                    $minTriggerActionThreshold = Stream::from($triggerActions)
                        ->filter(fn(TriggerAction $triggerAction) => $triggerAction->getConfig()['limit'] === TriggerAction::LOWER)
                        ->last();

                    $maxTriggerActionThreshold = Stream::from($triggerActions)
                        ->filter(fn(TriggerAction $triggerAction) => $triggerAction->getConfig()['limit'] === TriggerAction::HIGHER)
                        ->last();

                    $minThreshold = $minTriggerActionThreshold?->getConfig()['temperature'];
                    $maxThreshold = $maxTriggerActionThreshold?->getConfig()['temperature'];
                }
                if(!$transportRound->getEndedAt()) {
                    $end = clone ($transportRound->getBeganAt() ?? new DateTime("now"));
                    $end->setTime(23, 59);
                } else {
                    $end = min((clone ($transportRound->getBeganAt()))->setTime(23, 59), $transportRound->getEndedAt());
                }

                $now = new DateTime();
                $urls[] = [
                    "fetch_url" => $router->generate("chart_data_history", [
                        "type" => IOTService::getEntityCodeFromEntity($location),
                        "id" => $location->getId(),
                        'start' => ($transportRound->getBeganAt() ?? $now)->format('Y-m-d\TH:i'),
                        'end' => $end->format('Y-m-d\TH:i'),
                        'messageContentType' => IOTService::DATA_TYPE_TEMPERATURE,
                    ], UrlGeneratorInterface::ABSOLUTE_URL),
                    "minTemp" => $minThreshold ?? 0,
                    "maxTemp" => $maxThreshold ?? 0,
                ];
            }
        }

        $hasSomeDelivery = Stream::from($transportRound->getTransportRoundLines())
            ->some(fn (TransportRoundLine $line) => $line->getOrder()?->getRequest() instanceof TransportDeliveryRequest);

        if (empty($urls)) {
            $urls[] = [
                "fetch_url" => $router->generate("chart_data_history", [
                    "type" => null,
                    "id" => null,
                    'start' => new DateTime('now'),
                    'end' => new DateTime('tomorrow'),
                ], UrlGeneratorInterface::ABSOLUTE_URL),
                "minTemp" => 0,
                "maxTemp" => 0,
            ];
        }

        return $this->render('transport/round/show.html.twig', [
            "transportRound" => $transportRound,
            "realTime" => $realTime,
            "calculationsPoints" => $calculationsPoints,
            "transportPoints" => $transportPoints,
            "delivererPosition" => $delivererPosition,
            "urls" => $urls,
            "roundDateBegan" => $transportDateBeganAt,
            "hasSomeDelivery" => $hasSomeDelivery,
            "hasExceededThresholdUnder" => $transportRound->isUnderThresholdExceeded(),
            "hasExceededThresholdOver" => $transportRound->isUpperThresholdExceeded(),
            "containsOnlyCollect" => Stream::from($transportRound->getTransportRoundLines())
                ->every(fn(TransportRoundLine $line) => $line->getOrder()->getRequest() instanceof TransportCollectRequest)
        ]);
    }

    #[Route("/calculer", name: "transport_round_calculate", options: ['expose' => true], methods: "GET")]
    #[HasPermission([Menu::ORDRE, Action::SCHEDULE_TRANSPORT_ROUND])]
    public function calculate(Request                $request,
                              GeoService             $geoService,
                              EntityManagerInterface $entityManager): Response {

        $orderRepository = $entityManager->getRepository(TransportOrder::class);

        $startingPointAddress = $geoService->fetchCoordinates($request->query->get('startingPoint'));
        $timeStartingPointAddress = $geoService->fetchCoordinates($request->query->get('timeStartingPoint'));
        $endingPointAddress = $geoService->fetchCoordinates($request->query->get('endingPoint'));

        $coordinates = [
            [
                'index' => 0,
                'coordinates' => [
                    'latitude' => $startingPointAddress[0],
                    'longitude' => $startingPointAddress[1],
                ]
            ],
            [
                'index' => 1,
                'coordinates' => [
                    'latitude' => $timeStartingPointAddress[0],
                    'longitude' => $timeStartingPointAddress[1],
                ]
            ],
            [
                'index' => count($request->query->all('orders')) + 2,
                'coordinates' => [
                    'latitude' => $endingPointAddress[0],
                    'longitude' => $endingPointAddress[1],
                ]
            ],
        ];

        $orders = Stream::from($request->query->all('orders'))
            ->sort(fn(array $order1, array $order2) => $order1['index'] <=> $order2['index'])
            ->keymap(fn(array $order) => [
                $order['index'],
                $orderRepository->find($order['order'])
            ])
            ->toArray();

        $coordinates = Stream::from($request->query->all('orders'))
            ->map(function(array $order) use ($orderRepository) {
                $orderEntity = $orderRepository->find($order['order']);
                return [
                    'index' => $order['index'] + 2,
                    'coordinates' => [
                        'latitude' => floatval($orderEntity->getRequest()->getContact()->getAddressLatitude())     ,
                        'longitude' => floatval($orderEntity->getRequest()->getContact()->getAddressLongitude()),
                    ]
                ];
            })
            ->concat($coordinates)
            ->sort(fn(array $coordinate1, array $coordinate2) => $coordinate1['index'] <=> $coordinate2['index'])
            ->keymap(fn(array $coordinates) => [$coordinates['index'], $coordinates['coordinates']])
            ->toArray();

        $roundData = $geoService->fetchStopsData($coordinates);

        foreach ($roundData['data'] as $key => $roundDatum) {
            if ($key > 0 && $key < count($roundData['data']) - 1) {
                $request = $orders[$key - 1]->getRequest();
                $type = $request instanceof TransportDeliveryRequest && !$request->getCollect()
                    ? 'deliveries'
                    :
                    ($request instanceof TransportCollectRequest && !$request->getDelivery()
                        ? 'collects'
                        : 'deliveryCollects'
                    );
                $roundData['data'][$key]['destinationType'] = $type;
            }
        }

        return new JsonResponse([
            'roundData' => $roundData,
            'coordinates' => $coordinates
        ]);
    }

    #[Route("/planifier", name: "transport_round_plan", options: ['expose' => true], methods: [self::GET])]
    #[HasPermission([Menu::ORDRE, Action::SCHEDULE_TRANSPORT_ROUND])]
    public function plan(Request $request,
                         EntityManagerInterface $entityManager,
                         SettingsService $settingsService,
                         UniqueNumberService $uniqueNumberService): Response {
        $isOnGoing = false;

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

            if (!$round || $round->getStatus()?->getCode() == TransportRound::STATUS_FINISHED) {
                throw new NotFoundHttpException('Impossible de planifier cette tournée');
            }

            $isOnGoing = $round->getStatus()?->getCode() == TransportRound::STATUS_ONGOING;
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

        if ($isOnGoing) {
            $transportOrders = Stream::from($transportOrders)
                ->filter(function (TransportOrder $order) {
                    return $order->getRequest() instanceof TransportCollectRequest;
                })
                ->toArray();
        }

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
                            ? ($request->getTimeSlot()?->getName() ?: $request->getExpectedAt()->format('H:i'))
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
            'isOnGoing' => $isOnGoing,
            'waitingTime' => json_encode([
                'deliveries' => $settingsService->getValue($entityManager, Setting::TRANSPORT_ROUND_DELIVERY_AVERAGE_TIME),
                'collects' => $settingsService->getValue($entityManager, Setting::TRANSPORT_ROUND_COLLECT_AVERAGE_TIME),
                'deliveryCollects' => $settingsService->getValue($entityManager, Setting::TRANSPORT_ROUND_DELIVERY_COLLECT_AVERAGE_TIME),
            ])
        ]);
    }

    #[Route("/save", name: "transport_round_save", options: ['expose' => true], methods: "POST", condition: 'request.isXmlHttpRequest()')]
    public function save(Request                 $request,
                         EntityManagerInterface  $entityManager,
                         StatusHistoryService    $statusHistoryService,
                         OperationHistoryService $operationHistoryService,
                         NotificationService     $notificationService,
                         UserService             $userService,
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
        $ordersAndTimes = json_decode($request->request->get('affectedOrders'), true);
        $user = $this->getUser();

        $expectedAt = FormatHelper::parseDatetime("$expectedAtDate $expectedAtTime");
        if (!$expectedAt) {
            throw new FormException('Format de la date attendue invalide');
        }

        $isNew = !$transportRoundId;

        if ($transportRoundId) {
            $transportRound = $transportRoundRepository->find($transportRoundId);

            if ($transportRound->getStatus()?->getCode() === TransportRound::STATUS_FINISHED) {
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

            $statusHistoryService->updateStatus($entityManager, $transportRound, $roundStatus, [
                "initiatedBy" => $user,
            ]);

            $entityManager->persist($transportRound);
        }

        $deliverer = $userRepository->find($deliverer);
        $roundIsOngoing = $transportRound->getStatus()?->getCode() === TransportRound::STATUS_ONGOING;

        if(!$roundIsOngoing) {
            $transportRound
                ->setExpectedAt($expectedAt)
                ->setDeliverer($deliverer)
                ->setStartPoint($startPoint)
                ->setStartPointScheduleCalculation($startPointScheduleCalculation)
                ->setEndPoint($endPoint);
        }
        $estimatedDistance = floatval($request->request->get('estimatedTotalDistance')) ?: null;

        if($estimatedDistance) {
            $estimatedDistance  *= 1000;
        }

        $transportRound
            ->setEstimatedDistance($estimatedDistance)
            ->setEstimatedTime($request->request->get('estimatedTotalTime'))
            ->setCoordinates($coordinates);

        if (empty($ordersAndTimes)) {
            throw new FormException("Il n'y a aucun ordre dans la tournée, veuillez réessayer");
        }

        $collectOrderAssignedStatus = $statusRepository
            ->findOneByCategorieNameAndStatutCode(CategorieStatut::TRANSPORT_ORDER_COLLECT,
                TransportOrder::STATUS_ASSIGNED);

        $deliveryOrderAssignedStatus = $statusRepository
            ->findOneByCategorieNameAndStatutCode(CategorieStatut::TRANSPORT_ORDER_DELIVERY,
                TransportOrder::STATUS_ASSIGNED);

        if($roundIsOngoing) {
            $collectOrderOngoingStatus = $statusRepository
                ->findOneByCategorieNameAndStatutCode(CategorieStatut::TRANSPORT_ORDER_COLLECT,
                    TransportOrder::STATUS_ONGOING);

            $deliveryOrderOngoingStatus = $statusRepository
                ->findOneByCategorieNameAndStatutCode(CategorieStatut::TRANSPORT_ORDER_DELIVERY,
                    TransportOrder::STATUS_ONGOING);

            $collectRequestOngoingStatus = $statusRepository
                ->findOneByCategorieNameAndStatutCode(CategorieStatut::TRANSPORT_REQUEST_COLLECT,
                    TransportRequest::STATUS_ONGOING);

            $deliveryRequestOngoingStatus = $statusRepository
                ->findOneByCategorieNameAndStatutCode(CategorieStatut::TRANSPORT_REQUEST_DELIVERY,
                    TransportRequest::STATUS_ONGOING);
        } else {
            $deliveryRequestOngoingStatus = null;
            $collectRequestOngoingStatus = null;
            $collectOrderOngoingStatus = null;
            $deliveryOrderOngoingStatus = null;
        }

        foreach ($ordersAndTimes as $ordersAndTime) {
            $orderId = $ordersAndTime['id'];
            $priority = $ordersAndTime['priority'];
            /** @var TransportOrder $transportOrder */
            $transportOrder = $transportOrderRepository->find($orderId);
            if ($transportOrder) {
                $affectationAllowed = (
                    $transportOrder->getStatus()->getCode() === TransportOrder::STATUS_TO_ASSIGN ||
                    $transportOrder->getTransportRoundLines()->isEmpty() ||
                    Stream::from ($transportOrder->getTransportRoundLines())
                        ->map(fn(TransportRoundLine $line) => $line->getTransportRound())
                        ->every(fn(TransportRound $round) => $round->getStatus()?->getCode() === TransportRound::STATUS_FINISHED || $round->getId() === $transportRound->getId())
                );
                if (!$affectationAllowed) {
                    throw new FormException("L'ordre n°{$priority} est déjà affecté à une tournée non terminée");
                }

                $line = $transportRound->getTransportRoundLine($transportOrder);
                if (!isset($line)) {
                    $transportRequest = $transportOrder->getRequest();
                    $line = new TransportRoundLine();
                    $line->setOrder($transportOrder);
                    $line->setRejectedAt(null);
                    $line->setFailedAt(null);

                    $entityManager->persist($line);
                    $transportRound->addTransportRoundLine($line);

                    // set transportOrder status + add status history + add transport history
                    $orderAssignedStatus = $transportRequest instanceof TransportDeliveryRequest ? $deliveryOrderAssignedStatus : $collectOrderAssignedStatus;
                    $orderOngoingStatus = $transportRequest instanceof TransportDeliveryRequest ? $deliveryOrderOngoingStatus : $collectOrderOngoingStatus;
                    $requestOngoingStatus = $transportRequest instanceof TransportDeliveryRequest ? $deliveryRequestOngoingStatus : $collectRequestOngoingStatus;

                    $orderStatusHistory = $statusHistoryService->updateStatus($entityManager, $transportOrder, $orderAssignedStatus, [
                        "initiatedBy" => $user,
                    ]);

                    if ($roundIsOngoing) {
                        $statusHistoryService->updateStatus($entityManager, $transportRequest, $requestOngoingStatus, [
                            "initiatedBy" => $user,
                        ]);
                        $orderStatusHistory = $statusHistoryService->updateStatus($entityManager, $transportOrder, $orderOngoingStatus, [
                            "initiatedBy" => $user,
                        ]);
                    }

                    $operationHistoryService->persistTransportHistory($entityManager, $transportOrder, OperationHistoryService::TYPE_AFFECTED_ROUND, [
                        'user' => $this->getUser(),
                        'deliverer' => $transportRound->getDeliverer(),
                        'round' => $transportRound,
                        'history' => $orderStatusHistory,
                    ]);

                    $operationHistoryService->persistTransportHistory($entityManager, $transportRequest,  OperationHistoryService::TYPE_REQUEST_AFFECTED_ROUND, [
                        'history' => $orderStatusHistory,
                    ]);

                    if($roundIsOngoing) {
                        $operationHistoryService->persistTransportHistory($entityManager, [$transportRequest, $transportOrder], OperationHistoryService::TYPE_ONGOING, [
                            "user" => $this->getUser(),
                            "history" => $orderStatusHistory,
                        ]);
                    }
                }

                if (isset($ordersAndTime['time'])) {
                    $roundExpectedAt = $transportRound->getExpectedAt() ?? new DateTime();
                    $estimated = clone $roundExpectedAt;
                    $estimated
                        ->setTime(
                            intval(substr($ordersAndTime['time'], 0, 2)),
                            intval(substr($ordersAndTime['time'], 3, 2))
                        );
                    $line
                        ->setEstimatedAt($estimated);
                }
                else {
                    throw new FormException("L'estimation de passage de l'ordre n°{$priority} n'est pas calculable, veuillez vérifier l'adresse saisie");
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

        $todaysRounds = $transportRoundRepository->findTodayRounds($deliverer);
        $entityManager->flush();
        if($isNew) {
            $now = (new DateTime())->format("d-m-Y");
            if (!empty($todaysRounds)
                && $now === $transportRound->getExpectedAt()->format("d-m-Y")) {
                $userChannel = $userService->getUserFCMChannel($deliverer);
                $notificationService->send($userChannel, "Une nouvelle tournée attribuée aujourd'hui", null, [
                    'type' => 'round',
                ]);
            }
        }

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

    #[Route('/csv', name: 'transport_rounds_export', options: ['expose' => true], methods: 'GET')]
    public function getTransportRoundCSV(Request                $request,
                                         CSVExportService       $CSVExportService,
                                         TransportRoundService  $transportRoundService,
                                         EntityManagerInterface $entityManager): Response {

        $transportRoundRepository = $entityManager->getRepository(TransportRound::class);
        $dateMin = $request->query->get('dateMin');
        $dateMax = $request->query->get('dateMax');

        $dateTimeMin = DateTime::createFromFormat('Y-m-d H:i:s', $dateMin . ' 00:00:00');
        $dateTimeMax = DateTime::createFromFormat('Y-m-d H:i:s', $dateMax . ' 23:59:59');

        $nameFile = 'export_tournee.csv';
        $csvHeader = [
            'N°Tournée',
            'Statut',
            'Date Tournée',
            'Date Attente livreur',
            'Date En cours',
            'Date Terminée',
            'Temps estimé',
            'Temps réel',
            'Kilomètres estimés',
            'Kilomètres réels',
            'Livreur',
            'Immatriculation',
            'Patient',
            'N°Demande',
            'Adresse Livraison',
            'Numéro dans la tournée',
            'Statut ordre transport',
            'Dépassement températures',
        ];

        $transportRoundsIterator = $transportRoundRepository->iterateTransportRoundsByDates($dateTimeMin, $dateTimeMax);

        return $CSVExportService->streamResponse(function ($output) use ($CSVExportService, $transportRoundService, $transportRoundsIterator) {
            /** @var TransportRound $round */
            foreach ($transportRoundsIterator as $round) {
                $transportRoundService->putLineRoundAndOrder($output, $CSVExportService, $round);
            }
        }, $nameFile, $csvHeader);
    }

    #[Route("/bon-de-transport/{transportRound}", name: "print_round_note", options: ['expose' => true], methods: "GET")]
    #[HasPermission([Menu::DEM, Action::DISPLAY_TRANSPORT])]
    public function printTransportNote(TransportRound $transportRound,
                                       PDFGeneratorService $pdfService): Response {

        return new PdfResponse(
            $pdfService->generatePDFTransportRound($transportRound),
            "{$transportRound->getNumber()}-bon-transport.pdf"
        );
    }

    #[Route("/last-deliverer-position/{transportRound}", name: "transport_round_last_deliverer_position", options: ['expose' => true], methods: "GET")]
    #[HasPermission([Menu::DEM, Action::DISPLAY_TRANSPORT])]
    public function getLastDelivererPosition(TransportRound         $transportRound,
                                             EntityManagerInterface $entityManager): JsonResponse {
        $delivererPosition = $transportRound?->getBeganAt()
            ? $entityManager->getRepository(Vehicle::class)->findOneByDateLastMessageBetween(
                $transportRound->getVehicle(),
                $transportRound->getBeganAt(),
                $transportRound->getEndedAt(),
                Sensor::GPS)
            : null;

        return new JsonResponse([
            "success" => true,
            "position" => $delivererPosition["content"] ?? null,
        ]);
    }
}
