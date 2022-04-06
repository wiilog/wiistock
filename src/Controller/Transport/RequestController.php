<?php

namespace App\Controller\Transport;

use App\Entity\CategorieCL;
use App\Annotation\HasPermission;
use App\Entity\Action;
use App\Entity\CategoryType;
use App\Entity\FreeField;
use App\Entity\Setting;
use App\Entity\StatusHistory;
use App\Entity\Transport\TransportCollectRequestLine;
use App\Entity\Transport\TransportDeliveryOrderPack;
use App\Entity\Transport\TransportDeliveryRequest;
use App\Entity\FiltreSup;
use App\Entity\Menu;
use App\Entity\Nature;
use App\Entity\Transport\TemperatureRange;
use App\Entity\Transport\TransportDeliveryRequestLine;
use App\Entity\Transport\TransportHistory;
use App\Entity\Transport\TransportRequest;
use App\Entity\Type;
use App\Exceptions\FormException;
use App\Helper\FormatHelper;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\Transport\TransportCollectRequest;
use App\Entity\Utilisateur;
use DateTime;
use App\Service\Transport\TransportService;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\InputBag;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Service\Attribute\Required;
use WiiCommon\Helper\Stream;


#[Route("transport/demande")]
class RequestController extends AbstractController {

    #[Required]
    public TransportService $transportService;

    /**
     * Used in AppController::index for landing page
     */
    #[Route("/liste", name: "transport_request_index", methods: "GET")]
    #[HasPermission([Menu::DEM, Action::DISPLAY_TRANSPORT])]
    public function index(EntityManagerInterface $entityManager): Response {
        $typeRepository = $entityManager->getRepository(Type::class);
        $natureRepository = $entityManager->getRepository(Nature::class);
        $temperatureRangeRepository = $entityManager->getRepository(TemperatureRange::class);

        return $this->render('transport/request/index.html.twig', [
            'newRequest' => new TransportDeliveryRequest(),
            'categories' => [
                [
                    "category" => CategoryType::DELIVERY_TRANSPORT,
                    "icon" => "cart-delivery",
                    "label" => "Livraison",
                ], [
                    "category" => CategoryType::COLLECT_TRANSPORT,
                    "icon" => "cart-collect",
                    "label" => "Collecte",
                ],
            ],
            'types' => $typeRepository->findByCategoryLabels([
                CategoryType::DELIVERY_TRANSPORT, CategoryType::COLLECT_TRANSPORT,
            ]),
            'natures' => $natureRepository->findByAllowedForms([
                Nature::TRANSPORT_COLLECT_CODE,
                Nature::TRANSPORT_DELIVERY_CODE
            ]),
            'temperatures' => $temperatureRangeRepository->findAll(),
            'statuts' => [
                TransportRequest::STATUS_TO_PREPARE,
                TransportRequest::STATUS_TO_DELIVER,
                TransportRequest::STATUS_AWAITING_PLANNING,
                TransportRequest::STATUS_TO_COLLECT,
                TransportRequest::STATUS_ONGOING,
                TransportRequest::STATUS_FINISHED,
                TransportRequest::STATUS_DEPOSITED,
                TransportRequest::STATUS_CANCELLED,
                TransportRequest::STATUS_NOT_DELIVERED,
                TransportRequest::STATUS_NOT_COLLECTED,
            ],
        ]);
    }

    #[Route("/voir/{transportRequest}", name: "transport_request_show", methods: "GET")]
    public function show(TransportRequest $transportRequest,
                         EntityManagerInterface $entityManager): Response {
        $freeFieldRepository = $entityManager->getRepository(FreeField::class);
        $typeRepository = $entityManager->getRepository(Type::class);
        $natureRepository = $entityManager->getRepository(Nature::class);
        $temperatureRangeRepository = $entityManager->getRepository(TemperatureRange::class);

        $categoryFF = $transportRequest instanceof TransportDeliveryRequest
            ? CategorieCL::DELIVERY_TRANSPORT
            : CategorieCL::COLLECT_TRANSPORT;
        $freeFields = $freeFieldRepository->findByTypeAndCategorieCLLabel($transportRequest->getType(), $categoryFF);

        $packsCount = !$transportRequest->getOrders()->isEmpty()
            ? $transportRequest->getOrders()->first()->getPacks()->count()
            : null;

        $hasRejectedPacks = !$transportRequest->getOrders()->isEmpty()
            && Stream::from($transportRequest->getOrders()->first()->getPacks())
                ->some(fn(TransportDeliveryOrderPack $pack) => $pack->isRejected());

        return $this->render('transport/request/show.html.twig', [
            'request' => $transportRequest,
            'freeFields' => $freeFields,
            "types" => $typeRepository->findByCategoryLabels([
                CategoryType::DELIVERY_TRANSPORT, CategoryType::COLLECT_TRANSPORT,
            ]),
            "natures" => $natureRepository->findByAllowedForms([
                Nature::TRANSPORT_COLLECT_CODE,
                Nature::TRANSPORT_DELIVERY_CODE
            ]),
            "temperatures" => $temperatureRangeRepository->findAll(),
            "packsCount" => $packsCount,
            "hasRejectedPacks" => $hasRejectedPacks
        ]);
    }

    #[Route("/new", name: "transport_request_new", options: ["expose" => true], methods: "POST", condition: "request.isXmlHttpRequest()")]
    #[HasPermission([Menu::DEM, Action::CREATE_TRANSPORT], mode: HasPermission::IN_JSON)]
    public function new(Request $request,
                        EntityManagerInterface $entityManager,
                        TransportService $transportService): JsonResponse {

        $settingRepository = $entityManager->getRepository(Setting::class);

        /** @var Utilisateur $user */
        $user = $this->getUser();
        $data = $request->request;

        $deliveryData = $data->has('delivery') ? json_decode($data->get('delivery'), true) : null;
        if (is_array($deliveryData) && !empty($deliveryData)) {
            $deliveryData['requestType'] = TransportRequest::DISCR_DELIVERY;
            /** @var TransportDeliveryRequest $transportDeliveryRequest */
            $transportDeliveryRequest = $transportService->persistTransportRequest($entityManager, $user, new InputBag($deliveryData));
        }

        $transportRequest = $transportService->persistTransportRequest($entityManager, $user, $data, $transportDeliveryRequest ?? null);

        $mainTransportRequest = $transportDeliveryRequest ?? $transportRequest;
        $validationMessage = null;
        if ($mainTransportRequest->getStatus()?->getCode() === TransportRequest::STATUS_AWAITING_VALIDATION) {
            $validationMessage = 'Votre demande de transport est en attente de validation';
        }
        else if ($mainTransportRequest->getStatus()?->getCode() === TransportRequest::STATUS_SUBCONTRACTED) {
            $settingMessage = $settingRepository->getOneParamByLabel(Setting::NON_BUSINESS_HOURS_MESSAGE);
            $settingMessage = $settingMessage ? "<br/><br/>$settingMessage" : '';
            $validationMessage = "
                <div class='text-center'>
                    Votre demande de transport va être prise en compte.<br/>
                    Le suivi en temps réel n'est pas disponible car elle est sur un horaire non ouvré.
                    {$settingMessage}
                </div>
            ";
        }

        $entityManager->flush();

        return $this->json([
            "success" => true,
            "message" => "Votre demande de transport a bien été créée",
            'validationMessage' => $validationMessage
        ]);
    }

    #[Route("/edit/{transportRequest}", name: "transport_request_edit", options: ["expose" => true], methods: "POST", condition: "request.isXmlHttpRequest()")]
    #[HasPermission([Menu::DEM, Action::EDIT_TRANSPORT], mode: HasPermission::IN_JSON)]
    public function edit(Request $request,
                         EntityManagerInterface $entityManager,
                         TransportService $transportService,
                         TransportRequest $transportRequest): JsonResponse {

        $transportService->updateTransportRequest($entityManager, $transportRequest, $request->request);
        $entityManager->flush();

        return $this->json([
            "success" => true,
            "message" => "Votre demande de transport a bien été mise à jour",
        ]);
    }

    #[Route('/api', name: 'transport_request_api', options: ['expose' => true], methods: 'POST', condition: 'request.isXmlHttpRequest()')]
    #[HasPermission([Menu::DEM, Action::DISPLAY_TRANSPORT], mode: HasPermission::IN_JSON)]
    public function api(Request $request, EntityManagerInterface $manager): Response {
        $filtreSupRepository = $manager->getRepository(FiltreSup::class);
        $transportRepository = $manager->getRepository(TransportRequest::class);

        $filters = $filtreSupRepository->getFieldAndValueByPageAndUser(FiltreSup::PAGE_TRANSPORT_REQUESTS, $this->getUser());
        $queryResult = $transportRepository->findByParamAndFilters($request->request, $filters);

        $transportRequests = [];
        foreach ($queryResult["data"] as $transportRequest) {
            $expectedAtStr = $transportRequest->getExpectedAt()?->format("dmY");
            if ($expectedAtStr) {
                $transportRequests[$expectedAtStr][] = $transportRequest;
            }
        }

        $rows = [];
        $currentRow = [];

        foreach ($transportRequests as $date => $requests) {
            $date = DateTime::createFromFormat("dmY", $date);
            $date = FormatHelper::longDate($date);

            $counts = Stream::from($requests)
                ->map(fn(TransportRequest $request) => get_class($request))
                ->reduce(function($carry, $class) {
                    $carry[$class] = ($carry[$class] ?? 0) + 1;
                    return $carry;
                }, []);

            $deliveryCount = $counts[TransportDeliveryRequest::class] ?? null;
            if($deliveryCount) {
                $s = $deliveryCount > 1 ? "s" : "";
                $deliveryCount = "<span class='wii-icon wii-icon-cart-delivery wii-icon-15px-primary mr-1'></span> $deliveryCount livraison$s";
            }

            $collectCount = $counts[TransportCollectRequest::class] ?? null;
            if($collectCount) {
                $s = $collectCount > 1 ? "s" : "";
                $collectCount = "<span class='wii-icon wii-icon-cart-collect wii-icon-15px-primary mr-1'></span> $collectCount collecte$s";
            }

            $row = "<div class='transport-list-date px-1 pb-2 pt-3'>$date <div class='transport-counts'>$deliveryCount $collectCount</div></div>";

            if(!$rows) {
                $export = "<span>
                    <button type='button' class='btn btn-primary mr-1'
                            onclick='saveExportFile(`transport_requests_export`)'>
                        <i class='fa fa-file-csv mr-2' style='padding: 0 2px'></i>
                        Exporter au format CSV
                    </button>
                </span>";

                $row = "<div class='d-flex flex-column-reverse flex-md-row justify-content-between'>$row $export</div>";
            }

            $rows[] = [
                "content" => $row,
            ];

            foreach ($requests as $transportRequest) {
                $currentRow[] = $this->renderView("transport/request/list_card.html.twig", [
                    "prefix" => "DTR",
                    "request" => $transportRequest,
                    "timeSlot" => $this->transportService->getTimeSlot($manager, $transportRequest->getExpectedAt()),
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

    #[Route("/supprimer/{transportRequest}", name: "transport_request_delete", options: ['expose' => true], methods: "DELETE")]
    public function delete(TransportRequest $transportRequest, EntityManagerInterface $entityManager): Response {

        $success = $transportRequest->canBeDeleted();

        if ($success) {
            // TODO supprimer la demande et toutes les données liées, il faut attendre que tout soit effectif (liaisons colis, ordres, ....)
            $msg = 'Demande supprimée.';
            $entityManager->remove($transportRequest);
            $entityManager->flush();
        }
        else {
            $msg = 'Le statut de cette demande rends impossible sa suppression.';
        }

        return $this->json([
            'success' => $success,
            'msg' => $msg,
            "reload" => true,
            'redirect' => $this->generateUrl('transport_request_index')
        ]);
    }

    #[Route("/{transportRequest}/status-history-api", name: "transport_request_status_history_api", options: ['expose' => true], methods: "GET")]
    public function statusHistoryApi(TransportRequest $transportRequest) {
        $round = !$transportRequest->getOrders()->isEmpty() && !$transportRequest->getOrders()->first()->getTransportRoundLines()->isEmpty()
            ? $transportRequest->getOrders()->first()->getTransportRoundLines()->last()
            : null;

        if ($transportRequest instanceof TransportDeliveryRequest) {
            $statusWorkflow = $transportRequest->isSubcontracted()
                ? TransportRequest::SUBCONTRACT_STATUS_WORKFLOW
                : TransportRequest::DELIVERY_CLASSIC_STATUS_WORKFLOW;
        }
        else if ($transportRequest instanceof TransportCollectRequest) {
            $statusWorkflow = TransportRequest::COLLECT_STATUS_WORKFLOW;
        }
        else {
            throw new RuntimeException('Unkown transport request type');
        }

        return $this->json([
            "success" => true,
            "template" => $this->renderView('transport/request/timelines/status-history.html.twig', [
                "statusWorkflow" => $statusWorkflow,
                "statusesHistory" => Stream::from($transportRequest->getStatusHistory())
                    ->map(fn(StatusHistory $statusHistory) => [
                        "status" => FormatHelper::status($statusHistory->getStatus()),
                        "date" => FormatHelper::longDate($statusHistory->getDate(), true, true)
                    ])
                    ->toArray(),
                "request" => $transportRequest,
                "round" => $round
            ]),
        ]);
    }

    #[Route("/{transportRequest}/transport-history-api", name: "transport_history_api", options: ['expose' => true], methods: "GET")]
    public function transportHistoryApi(TransportRequest $transportRequest) {
        return $this->json([
            "success" => true,
            "template" => $this->renderView('transport/request/timelines/transport-history.html.twig', [
                "request" => $transportRequest,
                "history" => Stream::from($transportRequest->getHistory())
                    ->sort(fn(TransportHistory $h1, TransportHistory $h2) => $h2->getDate() <=> $h1->getDate())
                    ->toArray()
            ]),
        ]);
    }

    #[Route("/{transportRequest}/transport-packs-api", name: "transport_packs_api", options: ['expose' => true], methods: "GET")]
    public function transportPacksApi(TransportRequest $transportRequest) {
        $transportCollectRequestLines = $transportRequest->getLines()->filter(fn($line) => $line instanceof TransportCollectRequestLine);
        $transportDeliveryRequestLines = $transportRequest->getLines()->filter(fn($line) => $line instanceof TransportDeliveryRequestLine);

        $packs = !$transportRequest->getOrders()->isEmpty() ? $transportRequest->getOrders()->first()->getPacks() : [];
        $associatedNaturesAndPacks = Stream::from($packs)
            ->keymap(function(TransportDeliveryOrderPack $transportDeliveryOrderPack) use ($packs) {
                $nature = $transportDeliveryOrderPack->getPack()->getNature();
                return [
                    $nature->getLabel(), Stream::from($packs)
                        ->filter(fn(TransportDeliveryOrderPack $pack) => $pack->getPack()->getNature() === $nature)
                        ->toArray()
                ];
        })->toArray();

        return $this->json([
            "success" => true,
            "template" => $this->renderView('transport/request/packs.html.twig', [
                "transportCollectRequestLines" => $transportCollectRequestLines,
                "transportDeliveryRequestLines" => $transportDeliveryRequestLines,
                "associatedNaturesAndPacks" => $associatedNaturesAndPacks,
                "transportRequest" => $transportRequest
            ]),
        ]);
    }


    #[Route("/collect-already-exists", name: "transport_request_collect_already_exists", options: ['expose' => true], methods: "GET")]
    public function collectAlreadyExists(EntityManagerInterface $entityManager,
                                         Request $request): JsonResponse {
        $transportCollectRequestRepository = $entityManager->getRepository(TransportCollectRequest::class);

        $fileNumber = $request->query->get('fileNumber');

        if (empty($fileNumber)) {
            throw new FormException('Requête invalide');
        }

        $result = $transportCollectRequestRepository->findOngoingByFileNumber($fileNumber);

        return $this->json([
            'exists' => !empty($result),
        ]);
    }

}
