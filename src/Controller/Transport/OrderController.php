<?php

namespace App\Controller\Transport;

use App\Annotation\HasPermission;
use App\Entity\Action;
use App\Entity\CategorieCL;
use App\Entity\CategorieStatut;
use App\Entity\CategoryType;
use App\Entity\FiltreSup;
use App\Entity\FreeField;
use App\Entity\Menu;
use App\Entity\Statut;
use App\Entity\Transport\CollectTimeSlot;
use App\Entity\Transport\TransportCollectRequest;
use App\Entity\Transport\TransportDeliveryOrderPack;
use App\Entity\Transport\TransportDeliveryRequest;
use App\Entity\Transport\TransportOrder;
use App\Entity\Transport\TransportRequest;
use App\Entity\Type;
use App\Helper\FormatHelper;
use App\Service\StatusHistoryService;
use App\Service\Transport\TransportHistoryService;
use App\Service\UserService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use WiiCommon\Helper\Stream;


#[Route("transport/ordre")]
class OrderController extends AbstractController {

    #[Route("/validate-time-slot", name: "validate_time_slot", options: ["expose" => true], methods: "POST")]
    public function settingsTimeSlot(EntityManagerInterface $entityManager,
                                     Request $request,
                                     TransportHistoryService $transportHistoryService,
                                     StatusHistoryService $statusHistoryService,
                                     UserService $userService){
        $data = json_decode($request->getContent(), true);

        $choosenDate = DateTime::createFromFormat("Y-m-d" , $data["dateCollect"]);
        $choosenDate->setTime(0, 0);
        if ($choosenDate >= new DateTime("today midnight")){
            $order = $entityManager->find(TransportOrder::class, $data["orderId"]);

            $order->getRequest()
                ->setTimeSlot($entityManager->find(CollectTimeSlot::class, $data["timeSlot"]))
                ->setValidatedDate($choosenDate);

            { //update the order's history
                $status = $entityManager->getRepository(Statut::class)
                    ->findOneByCategorieNameAndStatutCode(CategorieStatut::TRANSPORT_ORDER_COLLECT, TransportOrder::STATUS_TO_ASSIGN);

                $statusHistoryRequest = $statusHistoryService->updateStatus($entityManager, $order, $status);

                $transportHistoryService->persistTransportHistory($entityManager, $order, TransportHistoryService::TYPE_CONTACT_VALIDATED, [
                    'user' => $userService->getUser(),
                    'history' => $statusHistoryRequest
                ]);
            }

            { //update the request's history
                $status = $entityManager->getRepository(Statut::class)
                    ->findOneByCategorieNameAndStatutCode(CategorieStatut::TRANSPORT_REQUEST_COLLECT, TransportRequest::STATUS_TO_COLLECT);

                $statusHistoryRequest = $statusHistoryService->updateStatus($entityManager, $order->getRequest(), $status);

                $transportHistoryService->persistTransportHistory($entityManager, $order->getRequest(), TransportHistoryService::TYPE_CONTACT_VALIDATED, [
                    'user' => $userService->getUser(),
                    'history' => $statusHistoryRequest
                ]);
            }

            $entityManager->flush();
            return $this->json([
                "success" => true,
                "msg" => "La date de collecte a été modifiée avec succès",
            ]);
        }
        else{
            return $this->json([
                "success" => false,
                "msg" => "La date de collecte doit être supérieure à la date actuelle",
            ]);
        }
    }

    #[Route("/voir/{transport}", name: "transport_order_show", methods: "GET")]
    #[HasPermission([Menu::ORDRE, Action::DISPLAY_TRANSPORT])]
    public function show(TransportOrder $transport,
                         EntityManagerInterface $entityManager): Response {
        $transportRequest = $transport->getRequest();

        $freeFieldRepository = $entityManager->getRepository(FreeField::class);
        $categoryFF = $transportRequest instanceof TransportDeliveryRequest
            ? CategorieCL::DELIVERY_TRANSPORT
            : CategorieCL::COLLECT_TRANSPORT;
        $freeFields = $freeFieldRepository->findByTypeAndCategorieCLLabel($transportRequest->getType(), $categoryFF);

        $packsCount = $transportRequest->getOrder()?->getPacks()->count() ?: 0;

        $hasRejectedPacks = $transportRequest->getOrder()
            && Stream::from($transportRequest->getOrder()?->getPacks() ?: [])
                ->some(fn(TransportDeliveryOrderPack $pack) => $pack->isLoaded() === false);

        $round = !$transport->getTransportRoundLines()->isEmpty()
            ? $transport->getTransportRoundLines()->last()->getTransportRound()
            : null ;

        $timeSlots = $entityManager->getRepository(CollectTimeSlot::class)->findAll();

        return $this->render('transport/order/show.html.twig', [
            'order' => $transport,
            'freeFields' => $freeFields,
            'packsCount' => $packsCount,
            'hasRejectedPacks' => $hasRejectedPacks,
            'round' => $round,
            'timeSlots' => $timeSlots,
        ]);
    }

    #[Route("/liste", name: "transport_order_index", methods: "GET")]
    #[HasPermission([Menu::ORDRE, Action::DISPLAY_TRANSPORT])]
    public function index(EntityManagerInterface $manager): Response {
        $typeRepository = $manager->getRepository(Type::class);

        return $this->render('transport/order/index.html.twig', [
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
            'statuts' => [
                TransportOrder::STATUS_TO_CONTACT,
                TransportOrder::STATUS_TO_ASSIGN,
                TransportOrder::STATUS_ASSIGNED,
                TransportOrder::STATUS_ONGOING,
                TransportOrder::STATUS_FINISHED,
                TransportOrder::STATUS_DEPOSITED,
                TransportOrder::STATUS_CANCELLED,
                TransportOrder::STATUS_NOT_DELIVERED,
                TransportOrder::STATUS_NOT_COLLECTED,
            ],
        ]);
    }

    #[Route('/api', name: 'transport_order_api', options: ['expose' => true], methods: 'POST', condition: 'request.isXmlHttpRequest()')]
    #[HasPermission([Menu::ORDRE, Action::DISPLAY_TRANSPORT], mode: HasPermission::IN_JSON)]
    public function api(Request $request, EntityManagerInterface $manager): Response {
        $filtreSupRepository = $manager->getRepository(FiltreSup::class);
        $transportRepository = $manager->getRepository(TransportOrder::class);

        $filters = $filtreSupRepository->getFieldAndValueByPageAndUser(FiltreSup::PAGE_TRANSPORT_ORDERS, $this->getUser());

        $queryResult = $transportRepository->findByParamAndFilters($request->request, $filters);

        $transportOrders = Stream::from($queryResult["data"])
            ->keymap(function (TransportOrder $order) {
                $request = $order->getRequest();
                $date = $request->getValidatedDate() ?? $request->getExpectedAt();
                $key = $date->format("dmY");
                return [
                    $key,
                    $order
                ];
            }, true)
            ->toArray();

        $rows = [];
        $currentRow = [];

        foreach ($transportOrders as $date => $orders) {
            $date = DateTime::createFromFormat("dmY", $date);
            $date = FormatHelper::longDate($date);

            $counts = Stream::from($orders)
                ->map(fn(TransportOrder $order) => get_class($order->getRequest()))
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
                            onclick='saveExportFile(`transport_orders_export`)'>
                        <i class='fa fa-file-csv mr-2' style='padding: 0 2px'></i>
                        Exporter au format CSV
                    </button>
                </span>";

                $row = "<div class='d-flex flex-column-reverse flex-md-row justify-content-between'>$row $export</div>";
            }

            $rows[] = [
                "content" => $row,
            ];

            foreach ($orders as $order) {
                $currentRow[] = $this->renderView("transport/request/list_card.html.twig", [
                    "prefix" => TransportOrder::NUMBER_PREFIX,
                    "request" => $order->getRequest(),
                    "order" => $order,
                    "path" => "transport_order_show",
                    "displayDropdown" => false,
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

}
