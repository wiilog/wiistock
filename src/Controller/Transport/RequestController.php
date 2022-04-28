<?php

namespace App\Controller\Transport;

use App\Entity\CategorieCL;
use App\Annotation\HasPermission;
use App\Entity\Action;
use App\Entity\CategorieStatut;
use App\Entity\CategoryType;
use App\Entity\FreeField;
use App\Entity\Setting;
use App\Entity\StatusHistory;
use App\Entity\Transport\TransportCollectRequestLine;
use App\Entity\Statut;
use App\Entity\Transport\TransportDeliveryOrderPack;
use App\Entity\Transport\TransportDeliveryRequest;
use App\Entity\FiltreSup;
use App\Entity\Menu;
use App\Entity\Nature;
use App\Entity\Transport\TemperatureRange;
use App\Entity\Transport\TransportDeliveryRequestLine;
use App\Entity\Transport\TransportHistory;
use App\Entity\Transport\TransportOrder;
use App\Entity\Transport\TransportRequest;
use App\Entity\Transport\TransportRequestLine;
use App\Entity\Type;
use App\Exceptions\FormException;
use App\Helper\FormatHelper;
use App\Service\MailerService;
use App\Service\PackService;
use App\Service\PDFGeneratorService;
use App\Service\StatusHistoryService;
use App\Service\Transport\TransportHistoryService;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\Transport\TransportCollectRequest;
use App\Entity\Utilisateur;
use DateTime;
use App\Service\Transport\TransportService;
use Knp\Bundle\SnappyBundle\Snappy\Response\PdfResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\InputBag;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\RouterInterface;
use Twig\Environment;
use WiiCommon\Helper\Stream;


#[Route("transport/demande")]
class RequestController extends AbstractController {

    /**
     * Used in AppController::index for landing page
     */
    #[Route("/liste", name: "transport_request_index", methods: "GET")]
    #[HasPermission([Menu::DEM, Action::DISPLAY_TRANSPORT])]
    public function index(EntityManagerInterface $entityManager, Request $request): Response {
        $typeRepository = $entityManager->getRepository(Type::class);
        $natureRepository = $entityManager->getRepository(Nature::class);
        $temperatureRangeRepository = $entityManager->getRepository(TemperatureRange::class);

        $token = $request->query->get('x-api-key');

        $tokenIsValid = isset($_SERVER['CLB_API_KEY']) && $token === $_SERVER['CLB_API_KEY'];
        $content = $request->query->get('content');
        if (!$content) {
            $response = false;
        } else {
            $content = str_replace(["\r", "\n"], ['\\r', '\\n'], $content);
            $response = json_decode($content, true);
        }
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
            'prefilled' => $tokenIsValid && $response,
            'informations' => $tokenIsValid && $response ? $response : [
                'Prenom' => '',
                'Nom' => '',
                'Nodos' => '',
                'Contact' => '',
                'Adresse' => '',
                'PersonnesAPrevenir' => '',
                'Remarques' => '',
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
                TransportRequest::STATUS_AWAITING_VALIDATION,
                TransportRequest::STATUS_TO_PREPARE,
                TransportRequest::STATUS_SUBCONTRACTED,
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

    #[Route("/voir/{id}", name: "transport_request_show", methods: "GET")]
    public function show(TransportRequest $id,
                         EntityManagerInterface $entityManager): Response {
        $transportRequest = $id;
        $freeFieldRepository = $entityManager->getRepository(FreeField::class);
        $typeRepository = $entityManager->getRepository(Type::class);
        $natureRepository = $entityManager->getRepository(Nature::class);
        $temperatureRangeRepository = $entityManager->getRepository(TemperatureRange::class);

        $categoryFF = $transportRequest instanceof TransportDeliveryRequest
            ? CategorieCL::DELIVERY_TRANSPORT
            : CategorieCL::COLLECT_TRANSPORT;
        $freeFields = $freeFieldRepository->findByTypeAndCategorieCLLabel($transportRequest->getType(), $categoryFF);

        $selectedLines = Stream::from($transportRequest->getLines())
            ->keymap(fn(TransportRequestLine $line) => [
                $line->getNature()?->getId() ?: 0,
                $line instanceof TransportDeliveryRequestLine
                    ? $line->getTemperatureRange()?->getId()
                    : ($line instanceof TransportCollectRequestLine ? $line->getQuantityToCollect() : null)
            ])
            ->filter(fn($_, $key) => $key !== 0)
            ->toArray();

        $packsCount = !$transportRequest->getOrders()->isEmpty()
            ? $transportRequest->getOrders()->last()->getPacks()->count()
            : 0;

        $hasRejectedPacks = !$transportRequest->getOrders()->isEmpty()
            && Stream::from($transportRequest->getOrders()->last()->getPacks())
                ->some(fn(TransportDeliveryOrderPack $pack) => $pack->isRejected());

        return $this->render('transport/request/show.html.twig', [
            'request' => $transportRequest,
            'selectedLines' => $selectedLines,
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
                        TransportService $transportService,
                        MailerService $mailerService,
                        Environment $templating,
                        RouterInterface $router): JsonResponse {

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
            $userRepository = $entityManager->getRepository(Utilisateur::class);
            $paramReceivers = $settingRepository->getOneParamByLabel(Setting::TRANSPORT_DELIVERY_DESTINATAIRES_MAIL);
            $receivers = $userRepository->findBy(['id' => explode(',', $paramReceivers)]);

            if(!empty($receivers)) {
                $mailerService->sendMail(
                    'FOLLOW GT // Nouvelle demande de transport à valider',
                    $templating->render('mails/contents/mailAwaitingTransportRequest.html.twig', [
                        'transportRequest' => $mainTransportRequest,
                        'urlSuffix' => $router->generate("transport_subcontract_index")
                    ]),
                    $receivers
                );
            }
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
            "transportRequestId" => $transportRequest->getId(),
            'validationMessage' => $validationMessage
        ]);
    }

    #[Route("/edit/{transportRequest}", name: "transport_request_edit", options: ["expose" => true], methods: "POST", condition: "request.isXmlHttpRequest()")]
    #[HasPermission([Menu::DEM, Action::EDIT_TRANSPORT], mode: HasPermission::IN_JSON)]
    public function edit(Request $request,
                         EntityManagerInterface $entityManager,
                         TransportService $transportService,
                         TransportRequest $transportRequest): JsonResponse {

        $transportService->updateTransportRequest($entityManager, $transportRequest, $request->request, $this->getUser());
        $entityManager->flush();

        return $this->json([
            "success" => true,
            "message" => "Votre demande de transport a bien été mise à jour",
        ]);
    }

    #[Route("/packing-api/{transportRequest}", name: "transport_request_packing_api", options: ["expose" => true], methods: "GET", condition: "request.isXmlHttpRequest()")]
    public function packingApi(TransportDeliveryRequest $transportRequest): JsonResponse {
        return $this->json([
            "success" => true,
            "html" => $this->renderView('transport/request/packing-content.html.twig', [
                'request' => $transportRequest
            ]),
        ]);
    }

    #[Route("/packing/{transportRequest}", name: "transport_request_packing", options: ["expose" => true], methods: "POST", condition: "request.isXmlHttpRequest()")]
    #[HasPermission([Menu::DEM, Action::EDIT_TRANSPORT], mode: HasPermission::IN_JSON)]
    public function packing(Request $request,
                            EntityManagerInterface $entityManager,
                            PackService $packService,
                            TransportHistoryService $transportHistoryService,
                            StatusHistoryService $statusHistoryService,
                            TransportRequest $transportRequest ): JsonResponse {
        $data = $request->request->all();
        $natureRepository = $entityManager->getRepository(Nature::class);
        $statusRepository = $entityManager->getRepository(Statut::class);
        $order = $transportRequest->getOrders()->last() ?: null;

        $canPacking = (
            isset($order)
            && $order->getPacks()->isEmpty()
            && $transportRequest instanceof TransportDeliveryRequest
            && in_array($transportRequest->getStatus()?->getCode(), [TransportRequest::STATUS_TO_PREPARE, TransportRequest::STATUS_SUBCONTRACTED])
        );

        if (!$canPacking) {
            throw new FormException("Impossible d'effectuer un colisage pour cette demande");
        }

        foreach($data as $natureId => $quantity){
            $nature = $natureRepository->find($natureId);
            if ($quantity > 0 && $nature) {
                for ($packIndex = 0; $packIndex < $quantity; $packIndex++) {
                    $orderLine = new TransportDeliveryOrderPack();
                    $orderLine->setOrder($order);
                    $pack = $packService->createPack([
                        'orderLine' => $orderLine,
                        'nature' => $nature,
                    ]);
                    $entityManager->persist($orderLine);
                    $entityManager->persist($pack);
                }
            }
            else {
                throw new FormException("Formulaire mal complété, veuillez réessayer");
            }
        }
        if($transportRequest->getStatus()->getCode() == TransportRequest::STATUS_TO_PREPARE) {
            $status = $statusRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::TRANSPORT_REQUEST_DELIVERY, TransportRequest::STATUS_TO_DELIVER);
            $statusHistory = $statusHistoryService->updateStatus($entityManager, $transportRequest, $status);
        }

        $transportHistoryService->persistTransportHistory($entityManager, $transportRequest, TransportHistoryService::TYPE_LABELS_PRINTING, [
            'history' => $statusHistory ?? null,
            'user' => $this->getUser()
        ]);

        $entityManager->flush();
        return $this->json([
            "success" => true,
            "message" => "Votre demande de transport a bien été mise à jour",
        ]);
    }

    #[Route("/{transportRequest}/packing-check", name: "transport_request_packing_check", options: ["expose" => true], methods: "POST", condition: "request.isXmlHttpRequest()")]
    #[HasPermission([Menu::DEM, Action::EDIT_TRANSPORT], mode: HasPermission::IN_JSON)]
    public function packingCheck(TransportRequest $transportRequest ): JsonResponse {
        $order = $transportRequest->getOrders()->last() ?: null;
        if($order->getPacks()->isEmpty()) {
            return $this->json([
                "success" => true,
                "message" => "Colisage possible",
            ]);
        }
        else {
            return $this->json([
                "success" => false,
                "message" => "Colisage déjà effectué",
            ]);
        }
    }

    #[Route('/api', name: 'transport_request_api', options: ['expose' => true], methods: 'POST', condition: 'request.isXmlHttpRequest()')]
    #[HasPermission([Menu::DEM, Action::DISPLAY_TRANSPORT], mode: HasPermission::IN_JSON)]
    public function api(Request                $request,
                        TransportService       $transportService,
                        EntityManagerInterface $entityManager): Response {
        $filtreSupRepository = $entityManager->getRepository(FiltreSup::class);
        $transportRepository = $entityManager->getRepository(TransportRequest::class);

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
                    "prefix" => TransportRequest::NUMBER_PREFIX,
                    "request" => $transportRequest,
                    "timeSlot" => $transportService->getTimeSlot($entityManager, $transportRequest->getExpectedAt()),
                    "path" => "transport_request_show"
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

            /**
             * @var TransportOrder $transportOrder
             */
            $transportOrder = $transportRequest->getOrders()->first();

            /**
             * @var StatusHistory[] $statusesHistories
             */
            $statusesHistories = Stream::from($transportRequest->getStatusHistory())
                ->concat($transportOrder->getStatusHistory())
                ->toArray();

            /**
             * @var TransportHistory[] $histories
             */
            $histories = Stream::from($transportRequest->getHistory())
                ->concat($transportOrder->getHistory())
                ->toArray();

            foreach ($statusesHistories as $status) {
                $transportRequest->removeStatusHistory($status);
                $transportOrder->removeStatusHistory($status);
                $entityManager->remove($status);
            }

            foreach ($histories as $history) {
                $transportRequest->removeHistory($history);
                $transportOrder->removeHistory($history);
                $entityManager->remove($history);
            }

            $entityManager->flush();
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

    #[Route("/annuler/{transportRequest}", name: "transport_request_cancel", options: ['expose' => true], methods: "POST")]
    public function cancel(TransportRequest $transportRequest,
                           TransportHistoryService $transportHistoryService,
                           StatusHistoryService $statusHistoryService,
                           EntityManagerInterface $entityManager): Response {

        /** @var Utilisateur $loggedUser */
        $loggedUser = $this->getUser();

        $success = $transportRequest->canBeCancelled();
        $statusRepository = $entityManager->getRepository(Statut::class);
        if ($transportRequest instanceof TransportDeliveryRequest) {
            $categoryRequest = CategorieStatut::TRANSPORT_REQUEST_DELIVERY;
            $categoryOrder = CategorieStatut::TRANSPORT_ORDER_DELIVERY;
        }
        else if ($transportRequest instanceof TransportCollectRequest) {
            $categoryRequest = CategorieStatut::TRANSPORT_REQUEST_COLLECT;
            $categoryOrder = CategorieStatut::TRANSPORT_ORDER_COLLECT;
        }
        else {
            throw new \RuntimeException('Unknown request type');
        }

        $statusRequest = $statusRepository->findOneByCategorieNameAndStatutCode($categoryRequest, TransportRequest::STATUS_CANCELLED);

        if ($success) {
            $msg = 'Demande annulée.';

            $statusHistoryRequest = $statusHistoryService->updateStatus($entityManager, $transportRequest, $statusRequest);
            $transportHistoryService->persistTransportHistory($entityManager, $transportRequest, TransportHistoryService::TYPE_CANCELLED, [
                'history' => $statusHistoryRequest,
                'user' => $loggedUser
            ]);

            $transportOrder = $transportRequest->getOrders()->last();
            if ($transportOrder) {
                $statusOrder = $statusRepository->findOneByCategorieNameAndStatutCode($categoryOrder, TransportOrder::STATUS_CANCELLED);
                $statusHistoryOrder = $statusHistoryService->updateStatus($entityManager, $transportOrder, $statusOrder);
                $transportHistoryService->persistTransportHistory($entityManager, $transportOrder, TransportHistoryService::TYPE_CANCELLED, [
                    'history' => $statusHistoryOrder,
                    'user' => $loggedUser
                ]);
            }

            $entityManager->flush();
        }
        else {
            $msg = 'Le statut de cette demande rends impossible son annulation.';
        }

        return $this->json([
            'success' => $success,
            'msg' => $msg,
            "reload" => true,
            'redirect' => $this->generateUrl('transport_request_index')
        ]);
    }

    #[Route("/{transportRequest}/print-transport-packs", name: "print_transport_packs", options: ['expose' => true], methods: "GET")]
    public function printTransportPacks(TransportRequest $transportRequest,
                                        PDFGeneratorService $PDFGeneratorService,
                                        EntityManagerInterface $manager): Response {
        $packs = !$transportRequest->getOrders()->isEmpty() ? $transportRequest->getOrders()->last()->getPacks() : [];
        $contact = $transportRequest->getContact();
        $contactName = $contact->getName();
        $contactFileNumber = $contact->getFileNumber();
        $contactAdress = $contact->getAddress();

        $contactAdress = preg_replace('/\s(\d{5})/', "\n$1", $contactAdress);

        $maxLineLength = 40;
        $cleanedContactAdress = Stream::explode("\n", $contactAdress)
            ->flatMap(function (string $part) use ($maxLineLength) {
                $part = trim($part);
                $lineLength = strlen($part);
                if ($lineLength > $maxLineLength) {
                    $results = [];

                    while (!empty($part)) {
                        $words = explode(" ", $part);
                        $finalPart = "";
                        foreach ($words as $word) {
                            if (empty($finalPart) || strlen($finalPart) + strlen($word) < $maxLineLength) {
                                if (!empty($finalPart)) {
                                    $finalPart .= " ";
                                }
                                $finalPart .= $word;
                            } else {
                                break;
                            }
                        }
                        $results[] = trim($finalPart);
                        if (strlen($finalPart) < strlen($part)) {
                            $part = trim(substr($part, strlen($finalPart)));
                        } else {
                            break;
                        }
                    }
                    return $results;
                } else {
                    return [$part];
                }
            })
            ->filterMap(fn(string $line) => trim($line))
            ->toArray();
        $logo = $manager->getRepository(Setting::class)->getOneParamByLabel(Setting::LABEL_LOGO);

        $temperatureRanges = Stream::from($transportRequest->getLines())
            ->filter(fn($line) => $line instanceof TransportDeliveryRequestLine)
            ->keymap(function(TransportDeliveryRequestLine $line) {
                return [$line->getNature()->getLabel(), $line->getTemperatureRange()?->getValue()];
            })->toArray();
        $config = [];
        $total = $packs->count();
        foreach ($packs as $index => $pack) {
            $position = $index + 1;
            $config[] = [
                'code' => $pack->getPack()->getCode(),
                'labels' => [
                    "$contactName - $contactFileNumber",
                    ...$cleanedContactAdress,
                    ($temperatureRanges[$pack->getPack()->getNature()->getLabel()] ?? '- ') . " °C",
                    "$position/$total"
                ],
                'logo' => $logo
            ];
        }

        $fileName = $PDFGeneratorService->getBarcodeFileName($config, 'transport');
        return new PdfResponse(
            $PDFGeneratorService->generatePDFBarCodes($fileName, $config, true),
            $fileName
        );
    }

}
