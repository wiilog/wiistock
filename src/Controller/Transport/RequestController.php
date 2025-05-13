<?php

namespace App\Controller\Transport;

use App\Annotation\HasPermission;
use App\Controller\AbstractController;
use App\Entity\Action;
use App\Entity\CategorieCL;
use App\Entity\CategorieStatut;
use App\Entity\FiltreSup;
use App\Entity\FreeField\FreeField;
use App\Entity\Menu;
use App\Entity\Nature;
use App\Entity\OperationHistory\TransportHistoryRecord;
use App\Entity\Setting;
use App\Entity\StatusHistory;
use App\Entity\Statut;
use App\Entity\Transport\TemperatureRange;
use App\Entity\Transport\TransportCollectRequest;
use App\Entity\Transport\TransportCollectRequestLine;
use App\Entity\Transport\TransportDeliveryOrderPack;
use App\Entity\Transport\TransportDeliveryRequest;
use App\Entity\Transport\TransportDeliveryRequestLine;
use App\Entity\Transport\TransportOrder;
use App\Entity\Transport\TransportRequest;
use App\Entity\Type\CategoryType;
use App\Entity\Type\Type;
use App\Entity\Utilisateur;
use App\Exceptions\FormException;
use App\Helper\FormatHelper;
use App\Service\CSVExportService;
use App\Service\FreeFieldService;
use App\Service\MailerService;
use App\Service\NotificationService;
use App\Service\OperationHistoryService;
use App\Service\PDFGeneratorService;
use App\Service\SettingsService;
use App\Service\StatusHistoryService;
use App\Service\StringService;
use App\Service\TranslationService;
use App\Service\Transport\TransportService;
use App\Service\UserService;
use DateTime;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Bundle\SnappyBundle\Snappy\Response\PdfResponse;
use RuntimeException;
use Symfony\Component\HttpFoundation\InputBag;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
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
    public function index(EntityManagerInterface    $entityManager,
                          Request                   $request,
                          TranslationService        $translation): Response {
        $typeRepository = $entityManager->getRepository(Type::class);
        $natureRepository = $entityManager->getRepository(Nature::class);
        $temperatureRangeRepository = $entityManager->getRepository(TemperatureRange::class);

        $natures = $natureRepository->findByAllowedForms([Nature::TRANSPORT_COLLECT_CODE, Nature::TRANSPORT_DELIVERY_CODE]);
        $requestLines = Stream::from($natures)
            ->sort(fn(Nature $a, Nature $b) => StringService::mbstrcmp(
                $this->getFormatter()->nature($a) ?? '',
                $this->getFormatter()->nature($b) ?? ''
            ))
            ->map(fn(Nature $nature) => [
                'nature' => $nature,
            ])
            ->toArray();

        return $this->render('transport/request/index.html.twig', [
            'newRequest' => new TransportDeliveryRequest(),
            'categories' => [
                [
                    "category" => CategoryType::DELIVERY_TRANSPORT,
                    "icon" => "cart-delivery",
                    "label" => $translation->translate("Demande", "Livraison", "Livraison", false),
                ], [
                    "category" => CategoryType::COLLECT_TRANSPORT,
                    "icon" => "cart-collect",
                    "label" => "Collecte",
                ],
            ],
            'types' => $typeRepository->findByCategoryLabels([
                CategoryType::DELIVERY_TRANSPORT,
                CategoryType::COLLECT_TRANSPORT,
            ]),
            'requestLines' => $requestLines,
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

    #[Route("/voir/{transport}", name: "transport_request_show", methods: "GET")]
    public function show(TransportRequest       $transport,
                         EntityManagerInterface $entityManager,
                         TransportService       $transportService): Response {
        $freeFieldRepository = $entityManager->getRepository(FreeField::class);

        $categoryFF = $transport instanceof TransportDeliveryRequest
            ? CategorieCL::DELIVERY_TRANSPORT
            : CategorieCL::COLLECT_TRANSPORT;
        $freeFields = $freeFieldRepository->findByTypeAndCategorieCLLabel($transport->getType(), $categoryFF);

        $order = $transport->getOrder();

        $orderPacks = $order?->getPacks();
        $packsCount = $orderPacks?->count() ?: 0;

        $hasRejectedPacks =  $order
            && Stream::from($orderPacks ?: [])
                ->some(fn(TransportDeliveryOrderPack $orderPack) => $orderPack->getState() === TransportDeliveryOrderPack::REJECTED_STATE);

        $contactPosition = [$transport->getContact()->getAddressLatitude(), $transport->getContact()->getAddressLongitude()];

        if($order && $order->getTransportRoundLines()->count()) {
            $round = $order->getTransportRoundLines()->last()->getTransportRound();
        } else {
            $round = null;
        }

        if ($round) {
            if(!$round->getEndedAt()) {
                $end = clone ($round->getBeganAt() ?? new DateTime("now"));
                $end->setTime(23, 59);
            } else {
                $end = min((clone ($round->getBeganAt()))->setTime(23, 59), $round->getEndedAt());
            }
            $urls = $transportService->getTemperatureChartConfig($round);
        }

        return $this->render('transport/request/show.html.twig', [
            'request' => $transport,
            'freeFields' => $freeFields,
            "packsCount" => $packsCount,
            "hasRejectedPacks" => $hasRejectedPacks,
            "contactPosition" => $contactPosition,
            'urls' => $urls ?? null,
            'roundId' => $round?->getId() ?? null,
        ]);
    }

    #[Route("/new", name: "transport_request_new", options: ["expose" => true], methods: [self::POST], condition: self::IS_XML_HTTP_REQUEST)]
    #[HasPermission([Menu::DEM, Action::CREATE_TRANSPORT], mode: HasPermission::IN_JSON)]
    public function new(Request                $request,
                        EntityManagerInterface $entityManager,
                        SettingsService        $settingsService,
                        TransportService       $transportService,
                        MailerService          $mailerService,
                        Environment            $templating,
                        TranslationService     $translationService,
                        RouterInterface        $router): JsonResponse {

        $prefixDeliveryRequest = TransportRequest::NUMBER_PREFIX;
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
        $transportDeliveryRequest = $mainTransportRequest instanceof TransportDeliveryRequest ? $mainTransportRequest : null;
        $transportCollectRequest = $mainTransportRequest instanceof TransportCollectRequest ? $mainTransportRequest : $transportDeliveryRequest?->getCollect();

        $validationMessage = null;
        if ($mainTransportRequest->getStatus()?->getCode() === TransportRequest::STATUS_AWAITING_VALIDATION) {
            $userRepository = $entityManager->getRepository(Utilisateur::class);
            $paramReceivers = $settingsService->getValue($entityManager, Setting::TRANSPORT_DELIVERY_DESTINATAIRES_MAIL);
            $receivers = $userRepository->findBy(['id' => explode(',', $paramReceivers)]);

            if(!empty($receivers)) {
                $mailerService->sendMail(
                    $entityManager,
                    $translationService->translate('Général', null, 'Header', 'Wiilog', false) . MailerService::OBJECT_SEPARATOR . 'Nouvelle demande de transport à valider',
                    $templating->render('mails/contents/mailAwaitingTransportRequest.html.twig', [
                        'transportRequest' => $mainTransportRequest,
                        'urlSuffix' => $router->generate("transport_subcontract_index"),
                        'prefix' => $prefixDeliveryRequest
                    ]),
                    $receivers
                );
            }
            $validationMessage = 'Votre demande de transport est en attente de validation';
        }
        else if ($mainTransportRequest->getStatus()?->getCode() === TransportRequest::STATUS_SUBCONTRACTED) {
            $settingMessage = $settingsService->getValue($entityManager, Setting::NON_BUSINESS_HOURS_MESSAGE);
            $settingMessage = $settingMessage ? "<br/><br/>$settingMessage" : '';
            $validationMessage = "
                <div class='text-center'>
                    Votre demande de transport va être prise en compte.<br/>
                    Le suivi en temps réel n'est pas disponible car elle est sur un horaire non ouvré.
                    {$settingMessage}
                </div>
            ";
        }

        try {
            $entityManager->flush();
        }
            /** @noinspection PhpRedundantCatchClauseInspection */
        catch (UniqueConstraintViolationException) {
            return new JsonResponse([
                'success' => false,
                'msg' => 'Une autre demande de transport est en cours de création, veuillez réessayer'
            ]);
        }

        return $this->json([
            "success" => true,
            "message" => "Votre demande de transport a bien été créée",
            "deliveryId" => $transportDeliveryRequest?->getId(),
            "collectId" => $transportCollectRequest?->getId(),
            'validationMessage' => $validationMessage
        ]);
    }

    #[Route("/edit/{transportRequest}", name: "transport_request_edit", options: ["expose" => true], methods: "POST", condition: "request.isXmlHttpRequest()")]
    #[HasPermission([Menu::DEM, Action::EDIT_TRANSPORT], mode: HasPermission::IN_JSON)]
    public function edit(Request $request,
                         EntityManagerInterface $entityManager,
                         TransportService $transportService,
                         TransportRequest $transportRequest): JsonResponse {

        $result = $transportService->updateTransportRequest($entityManager, $transportRequest, $request->request, $this->getUser());

        $entityManager->flush();

        $createdPacks = Stream::from($result['createdPacks'])
            ->map(fn(TransportDeliveryOrderPack $pack) => $pack->getId())
            ->toArray();

        return $this->json([
            "success" => true,
            "message" => "Votre demande de transport a bien été mise à jour",
            "createdPacks" => $createdPacks
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
    public function packing(Request                 $request,
                            EntityManagerInterface  $entityManager,
                            TransportService        $transportService,
                            OperationHistoryService $operationHistoryService,
                            StatusHistoryService    $statusHistoryService,
                            TransportRequest        $transportRequest ): JsonResponse {
        $data = $request->request->all();
        $natureRepository = $entityManager->getRepository(Nature::class);
        $statusRepository = $entityManager->getRepository(Statut::class);
        $order = $transportRequest->getOrder();

        $currentUser = $this->getUser();

        $canPacking = (
            isset($order)
            && $transportRequest instanceof TransportDeliveryRequest
            && in_array($transportRequest->getStatus()?->getCode(), [
                TransportRequest::STATUS_TO_PREPARE,
                TransportRequest::STATUS_SUBCONTRACTED,
                TransportRequest::STATUS_AWAITING_VALIDATION
            ])
        );

        if (!$canPacking) {
            throw new FormException("Impossible d'effectuer un colisage pour cette demande");
        }

        foreach($data as $natureId => $quantity){
            $nature = $natureRepository->find($natureId);
            if ($quantity > 0 && $nature) {
                for ($packIndex = 0; $packIndex < $quantity; $packIndex++) {
                    $transportService->persistDeliveryPack($entityManager, $order, $nature);
                }
            }
            else {
                throw new FormException("Formulaire mal complété, veuillez réessayer");
            }
        }

        if($transportRequest->getStatus()->getCode() == TransportRequest::STATUS_TO_PREPARE) {
            $status = $statusRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::TRANSPORT_REQUEST_DELIVERY, TransportRequest::STATUS_TO_DELIVER);
            $statusHistoryService->updateStatus($entityManager, $transportRequest, $status, [
                "initiatedBy" => $currentUser,
            ]);
        }

        $operationHistoryService->persistTransportHistory($entityManager, $transportRequest, OperationHistoryService::TYPE_LABELS_PRINTING, [
            'user' => $currentUser,
        ]);

        $operationHistoryService->persistTransportHistory($entityManager, $order, OperationHistoryService::TYPE_LABELS_PRINTING, [
            'user' => $currentUser,
        ]);

        $entityManager->flush();
        return $this->json([
            "success" => true,
            "message" => "Votre demande de transport a bien été mise à jour",
        ]);
    }

    #[Route("/{transportRequest}/packing-check", name: "transport_request_packing_check", options: ["expose" => true], methods: "POST", condition: "request.isXmlHttpRequest()")]
    #[HasPermission([Menu::DEM, Action::EDIT_TRANSPORT], mode: HasPermission::IN_JSON)]
    public function packingCheck(TransportRequest $transportRequest): JsonResponse {
        $order = $transportRequest->getOrder();
        $line = $order->getTransportRoundLines()->last();
        $allPacksRejected = Stream::from($order->getPacks())
            ->every(fn(TransportDeliveryOrderPack $pack) => $pack->getRejectReason());
        if ($order->getPacks()->isEmpty()
            || $allPacksRejected
            || ($line && $line->getRejectedAt())) {
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
    public function api(Request                 $request,
                        TransportService        $transportService,
                        EntityManagerInterface  $entityManager,
                        TranslationService      $translation): Response {
        $filtreSupRepository = $entityManager->getRepository(FiltreSup::class);
        $transportRepository = $entityManager->getRepository(TransportRequest::class);

        $filters = $filtreSupRepository->getFieldAndValueByPageAndUser(FiltreSup::PAGE_TRANSPORT_REQUESTS, $this->getUser());
        $queryResult = $transportRepository->findByParamAndFilters($request->request, $filters);

        $transportRequests = Stream::from($queryResult["data"])
            ->keymap(function(TransportRequest $transportRequest) {
                $date = $transportRequest->getValidatedDate() ?? $transportRequest->getExpectedAt();
                $key = $date->format("dmY");
                return [
                    $key,
                    $transportRequest
                ];
            },true)->toArray();

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
                $deliveryCount = "<span class='wii-icon wii-icon-cart-delivery wii-icon-15px-primary mr-1'></span> $deliveryCount " . mb_strtolower($translation->translate("Demande", "Livraison", "Livraison", false)) . "$s";
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
                            onclick='saveExportFile(`transport_requests_export`, true, {}, true )'>
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
                $roundLine = $transportRequest->getOrder()?->getTransportRoundLines()->last();
                $currentRow[] = $this->renderView("transport/request/list_card.html.twig", [
                    "prefix" => TransportRequest::NUMBER_PREFIX,
                    "request" => $transportRequest,
                    "timeSlot" => $roundLine && $roundLine->getEstimatedAt() ? $transportService->hourToTimeSlot($entityManager, $roundLine->getEstimatedAt()->format("H:i")) : null,
                    "path" => "transport_request_show",
                    "displayDropdown" => true,
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
            // TODO supprimer la demande et toutes les données liées, il faut attendre que tout soit effectif (liaisons UL, ordres, ....)
            $msg = 'Demande supprimée.';

            /**
             * @var TransportOrder $transportOrder
             */
            $transportOrder = $transportRequest->getOrder();
            if($transportOrder) {
                /**
                 * @var StatusHistory[] $statusesHistories
                 */
                $statusesHistories = Stream::from($transportRequest->getStatusHistory())
                    ->concat($transportOrder->getStatusHistory())
                    ->toArray();

                /**
                 * @var TransportHistoryRecord[] $histories
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

                foreach($transportOrder->getPacks() as $pack) {
                    $transportOrder->removePack($pack);
                    $entityManager->remove($pack);
                }
           } else {
                $statusesHistories = $transportRequest->getStatusHistory();
                $histories = $transportRequest->getHistory();

                foreach ($statusesHistories as $status) {
                    $transportRequest->removeStatusHistory($status);
                    $entityManager->remove($status);
                }

                foreach ($histories as $history) {
                    $transportRequest->removeHistory($history);
                    $entityManager->remove($history);
                }
            }

            $entityManager->flush();
            $entityManager->remove($transportRequest);
            $entityManager->flush();
        }
        else {
            $msg = 'Le statut de cette demande rend impossible sa suppression.';
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
    public function cancel(TransportRequest        $transportRequest,
                           OperationHistoryService $operationHistoryService,
                           StatusHistoryService    $statusHistoryService,
                           EntityManagerInterface  $entityManager,
                           NotificationService     $notificationService,
                           UserService             $userService): Response {

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
            $msg = "Demande annulée";
            $transportOrder = $transportRequest->getOrder();

            $line = $transportOrder->getTransportRoundLines()->last();
            $round = $line->getTransportRound();

            $current = $round->getCurrentOnGoingLine();
            if($current && $line->getId() === $current->getId()) {
                $userChannel = $userService->getUserFCMChannel($round->getDeliverer());
                $notificationService->send($userChannel, "Votre prochain point de passage a été annulé", null, [
                    "type" => "transport",
                    "id" => $transportRequest->getId(),
                ]);
            }

            $statusHistoryRequest = $statusHistoryService->updateStatus($entityManager, $transportRequest, $statusRequest, [
                "initiatedBy" => $loggedUser,
            ]);;
            $operationHistoryService->persistTransportHistory($entityManager, $transportRequest, OperationHistoryService::TYPE_CANCELLED, [
                'history' => $statusHistoryRequest,
                'user' => $loggedUser
            ]);

            $statusOrder = $statusRepository->findOneByCategorieNameAndStatutCode($categoryOrder, TransportOrder::STATUS_CANCELLED);
            $statusHistoryOrder = $statusHistoryService->updateStatus($entityManager, $transportOrder, $statusOrder, [
                "initiatedBy" => $loggedUser,
            ]);
            $operationHistoryService->persistTransportHistory($entityManager, $transportOrder, OperationHistoryService::TYPE_CANCELLED, [
                'history' => $statusHistoryOrder,
                'user' => $loggedUser
            ]);

            $line->setCancelledAt(new DateTime());

            $entityManager->flush();
        }
        else {
            $msg = "Ce transport ne peut pas être annulé car il n'est pas en cours";
        }

        return $this->json([
            'success' => $success,
            'msg' => $msg,
            "reload" => true,
            'redirect' => $this->generateUrl('transport_request_index')
        ]);
    }

    #[Route("/{transportRequest}/print-transport-packs", name: "print_transport_packs", options: ['expose' => true], methods: [self::GET])]
    public function printTransportPacks(TransportRequest       $transportRequest,
                                        TransportService       $transportService,
                                        PDFGeneratorService    $PDFGeneratorService,
                                        Request                $request,
                                        EntityManagerInterface $entityManager,
                                        SettingsService        $settingsService): PdfResponse {

        $logo = $settingsService->getValue($entityManager, Setting::LABEL_LOGO);

        $packsFilter = Stream::explode(',', $request->query->get('packs'))
            ->filter()
            ->toArray();

        $config = $transportService->createPrintPackConfig($transportRequest, $logo, $packsFilter);

        $fileName = $PDFGeneratorService->getBarcodeFileName($config, 'transport');
        return new PdfResponse(
            $PDFGeneratorService->generatePDFBarCodes($fileName, $config, true),
            $fileName
        );
    }

    #[Route("/modifier-api/{transportRequest}", name: "transport_request_edit_api", options: ['expose' => true], methods: "GET")]
    #[HasPermission([Menu::DEM, Action::EDIT_TRANSPORT], mode: HasPermission::IN_JSON)]
    public function editTemplate(EntityManagerInterface $entityManager,
                                 TransportRequest       $transportRequest): JsonResponse {
        $natureRepository = $entityManager->getRepository(Nature::class);
        $temperatureRangeRepository = $entityManager->getRepository(TemperatureRange::class);
        $typeRepository = $entityManager->getRepository(Type::class);

        if ($transportRequest instanceof TransportCollectRequest) {
            $collectNatures = $natureRepository->findByAllowedForms([Nature::TRANSPORT_COLLECT_CODE]);
            $requestLines = Stream::from($collectNatures)
                ->sort(fn(Nature $a, Nature $b) => StringService::mbstrcmp(
                    $this->getFormatter()->nature($a) ?? '',
                    $this->getFormatter()->nature($b) ?? ''
                ))
                ->map(function(Nature $nature) use ($transportRequest) {
                    /** @var TransportCollectRequestLine $line */
                    $line = $transportRequest->getLine($nature);
                    return [
                        'selected' => (bool) $line,
                        'nature' => $nature,
                        'quantity' => $line?->getQuantityToCollect()
                    ];
                })
                ->toArray();
        }
        else if ($transportRequest instanceof TransportDeliveryRequest) {
            $deliveryNatures = $natureRepository->findByAllowedForms([Nature::TRANSPORT_DELIVERY_CODE]);
            $requestLines = Stream::from($deliveryNatures)
                ->sort(fn(Nature $a, Nature $b) => StringService::mbstrcmp(
                    $this->getFormatter()->nature($a) ?? '',
                    $this->getFormatter()->nature($b) ?? ''
                ))
                ->map(function(Nature $nature) use ($transportRequest) {
                    /** @var TransportDeliveryRequestLine $line */
                    $line = $transportRequest->getLine($nature);
                    return [
                        'nature' => $nature,
                        'selected' => (bool) $line,
                        'quantity' => ($line ? $transportRequest->getOrder()?->getPacksForLine($line)?->count() : 0) ?: null,
                        'temperatureRange' => $line?->getTemperatureRange()?->getId(),
                    ];
                })
                ->toArray();
        }
        else {
            throw new RuntimeException('Invalid request type');
        }

        $types = $typeRepository->findByCategoryLabels([
            CategoryType::DELIVERY_TRANSPORT,
            CategoryType::COLLECT_TRANSPORT,
        ]);

        return $this->json([
            'success' => true,
            'template' => $this->renderView('transport/request/form.html.twig', [
                "requestLines" => $requestLines,
                "request" => $transportRequest,
                "types" => $types,
                "temperatures" => $temperatureRangeRepository->findAll(),
            ])
       ]);
    }

    #[Route("/bon-de-transport/{transportRequest}", name: "print_transport_note", options: ['expose' => true], methods: "GET")]
    #[HasPermission([Menu::DEM, Action::DISPLAY_TRANSPORT])]
    public function printTransportNote(TransportRequest    $transportRequest,
                                       PDFGeneratorService $pdfService): Response {

        return new PdfResponse(
            $pdfService->generatePDFTransport($transportRequest),
            "{$transportRequest->getNumber()}-bon-transport.pdf"
        );
    }
    #[Route('/csv', name: 'transport_requests_export', options: ['expose' => true], methods: ['GET'])]
    public function getDeliveryRequestCSV(Request                $request,
                                          FreeFieldService       $freeFieldService,
                                          CSVExportService       $CSVExportService,
                                          EntityManagerInterface $entityManager,
                                          TransportService       $transportService): Response
    {
        $transportRequestRepository = $entityManager->getRepository(TransportRequest::class);
        $dateMin = $request->query->get('dateMin');
        $dateMax = $request->query->get('dateMax');
        $category = $request->query->get('category');
        $freeFieldsConfigDelivery = $freeFieldService->createExportArrayConfig($entityManager, [CategorieCL::DELIVERY_TRANSPORT]);
        $freeFieldsConfigCollect = $freeFieldService->createExportArrayConfig($entityManager, [CategorieCL::COLLECT_TRANSPORT]);

        $dateTimeMin = DateTime::createFromFormat('Y-m-d H:i:s', $dateMin . ' 00:00:00');
        $dateTimeMax = DateTime::createFromFormat('Y-m-d H:i:s', $dateMax . ' 23:59:59');

        if ($category === CategoryType::DELIVERY_TRANSPORT) {
            $nameFile = 'export_demande_livraison.csv';
            $transportHeader = [
                'N°demande',
                'Transport',
                'Type',
                'Statut',
                'Urgence',
                'Demandeur',
                'Patient',
                'N°Dossier',
                'Adresse de livraison',
                'Métropole',
                'Date attendue',
                'Date A valider',
                'Date A préparer',
                'Date A livrer',
                'Date Sous-traitées',
                'Date En cours',
                'Date Terminée/Non Livrée',
                'Commentaire'
            ];

            $packsHeader = [
                'Nature UL',
                'Nombre d\'UL à livrer',
                'Températures',
                'Code UL',
                'Ecarté',
                'Motif écartement',
                'Retourné le',
            ];
            $csvHeader = array_merge($transportHeader, $packsHeader, $freeFieldsConfigDelivery['freeFieldsHeader']);
        } else {
            $nameFile = 'export_demande_collecte.csv';
            $transportHeader = [
                'N°demande',
                'Transport',
                'Type',
                'Statut',
                'Demandeur',
                'Patient',
                'N°Dossier',
                'Adresse de livraison',
                'Métropole',
                'Date attendue',
                'Date validée avec le patient',
                'Date A valider',
                'Date En attente de planification',
                'Date A collecter',
                'Date En cours',
                'Date Terminée/Non Collectée',
                'Date Objets déposés',
                'Commentaire',
            ];

            $naturesHeader = [
                'Nature UL',
                'Quantité à collecter',
                'Quantités collectées',
            ];
            $csvHeader = array_merge($transportHeader, $naturesHeader, $freeFieldsConfigCollect['freeFieldsHeader']);
        }
        $transportRequestIterator = $transportRequestRepository->iterateTransportRequestByDates($dateTimeMin, $dateTimeMax, $category);

        return $CSVExportService->streamResponse(function ($output) use ($CSVExportService, $transportService, $freeFieldsConfigDelivery, $freeFieldsConfigCollect, $transportRequestIterator) {
            /** @var TransportRequest $request */
            foreach ($transportRequestIterator as $request) {
                if ($request instanceof TransportDeliveryRequest) {
                    $transportService->putLineRequest($output, $CSVExportService, $request, $freeFieldsConfigDelivery);
                }
                else {
                    $transportService->putLineRequest($output, $CSVExportService, $request, $freeFieldsConfigCollect);
                }
            }
        }, $nameFile, $csvHeader);
    }
}
