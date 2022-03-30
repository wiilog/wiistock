<?php

namespace App\Controller\Transport;

use App\Entity\CategorieCL;
use App\Annotation\HasPermission;
use App\Entity\Action;
use App\Entity\CategoryType;
use App\Entity\FreeField;
use App\Entity\StatusHistory;
use App\Entity\Transport\TransportDeliveryRequest;
use App\Entity\FiltreSup;
use App\Entity\Menu;
use App\Entity\Nature;
use App\Entity\Transport\TemperatureRange;
use App\Entity\Transport\TransportRequest;
use App\Entity\Type;
use App\Helper\FormatHelper;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\Transport\TransportCollectRequest;
use App\Entity\Utilisateur;
use DateTime;
use App\Service\Transport\TransportService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\InputBag;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use WiiCommon\Helper\Stream;


#[Route("transport/demande")]
class RequestController extends AbstractController {

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
                    "category" => CategoryType::DELIVERY_TRANSPORT_REQUEST,
                    "icon" => "cart-delivery",
                    "label" => "Livraison",
                ], [
                    "category" => CategoryType::COLLECT_TRANSPORT_REQUEST,
                    "icon" => "cart-collect",
                    "label" => "Collecte",
                ],
            ],
            'types' => $typeRepository->findByCategoryLabels([
                CategoryType::DELIVERY_TRANSPORT_REQUEST,
                CategoryType::COLLECT_TRANSPORT_REQUEST,
            ]),
            'natures' => $natureRepository->findByAllowedForms([
                Nature::TRANSPORT_COLLECT_CODE,
                Nature::TRANSPORT_DELIVERY_CODE
            ]),
            'temperatures' => $temperatureRangeRepository->findAll(),
            'statuts' => [
                TransportRequest::STATUS_AWAITING_VALIDATION,
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
    public function show(TransportRequest $transportRequest, EntityManagerInterface $manager): Response {
        $categoryFF = $transportRequest instanceof TransportDeliveryRequest
            ? CategorieCL::DELIVERY_TRANSPORT_REQUEST
            : CategorieCL::COLLECT_TRANSPORT_REQUEST;
        $freeFields = $manager->getRepository(FreeField::class)->findByTypeAndCategorieCLLabel($transportRequest->getType(), $categoryFF);

        return $this->render('transport/request/show.html.twig', [
            'request' => $transportRequest,
            'freeFields' => $freeFields,
        ]);
    }

    #[Route("/new", name: "transport_request_new", options: ["expose" => true], methods: "POST", condition: "request.isXmlHttpRequest()")]
    #[HasPermission([Menu::DEM, Action::CREATE_TRANSPORT], mode: HasPermission::IN_JSON)]
    public function new(Request $request,
                        EntityManagerInterface $entityManager,
                        TransportService $transportService): JsonResponse {

        /** @var Utilisateur $user */
        $user = $this->getUser();
        $data = $request->request;

        $deliveryData = $data->has('delivery') ? json_decode($data->get('delivery'), true) : null;
        if (is_array($deliveryData) && !empty($deliveryData)) {
            $deliveryData['requestType'] = TransportRequest::DISCR_DELIVERY;
            /** @var TransportDeliveryRequest $transportDeliveryRequest */
            $transportDeliveryRequest = $transportService->persistTransportRequest($entityManager, $user, new InputBag($deliveryData));
        }

        $transportService->persistTransportRequest($entityManager, $user, $data, $transportDeliveryRequest ?? null);

        $entityManager->flush();

        return $this->json([
            "success" => true,
            "message" => "Votre demande de transport a bien été créée",
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
            dump($transportRequest);
            $transportRequests[$transportRequest->getExpectedAt()->format("dmY")][] = $transportRequest;
        }

        $rows = [];
        $previousDate = null;
        $currentRow = [];

        function insertCurrentRow(&$rows, &$currentRow) {
            if ($currentRow) {
                $row = "<div class='transport-request-row row no-gutters'>" . join($currentRow) . "</div>";
                $rows[] = [
                    "content" => $row,
                ];

                $currentRow = [];
            }
        }

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
                    "request" => $transportRequest,
                ]);
            }

            insertCurrentRow($rows, $currentRow);
        }


        return $this->json([
            "data" => $rows,
            "recordsTotal" => $queryResult["total"],
            "recordsFiltered" => $queryResult["count"],
        ]);
    }

    #[Route("/supprimer/{transportRequest}", name: "delete_transport_request", options: ['expose' => true], methods: "DELETE")]
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

    #[Route("/{transportRequest}/status-history-api", name: "status_history_api", options: ['expose' => true], methods: "GET")]
    public function statusHistoryApi(TransportRequest $transportRequest) {
        $round = !$transportRequest->getOrders()->isEmpty() && !$transportRequest->getOrders()->first()->getTransportRoundLines()->isEmpty()
            ? $transportRequest->getOrders()->first()->getTransportRoundLines()->last()
            : null;
        return $this->json([
            "success" => true,
            "template" => $this->renderView('transport/request/timeline.html.twig', [
                "statusesHistory" => Stream::from($transportRequest->getStatusHistory())->map(fn(StatusHistory $statusHistory) => [
                    "status" => FormatHelper::status($statusHistory->getStatus()),
                    "date" => FormatHelper::longDate($statusHistory->getDate(), true, true)
                ])->toArray(),
                "transportRequest" => $transportRequest,
                "round" => $round
            ]),
        ]);
    }

}
