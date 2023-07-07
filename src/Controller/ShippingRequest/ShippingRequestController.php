<?php

namespace App\Controller\ShippingRequest;

use App\Annotation\HasPermission;
use App\Controller\AbstractController;
use App\Entity\Action;
use App\Entity\Attachment;
use App\Entity\Article;
use App\Entity\CategorieStatut;
use App\Entity\Language;
use App\Entity\Emplacement;
use App\Entity\FiltreSup;
use App\Entity\Menu;
use App\Entity\ReferenceArticle;
use App\Entity\MouvementStock;
use App\Entity\Nature;
use App\Entity\Setting;
use App\Entity\ShippingRequest\ShippingRequest;
use App\Entity\ShippingRequest\ShippingRequestPack;
use App\Entity\StatusHistory;
use App\Entity\ShippingRequest\ShippingRequestExpectedLine;
use App\Entity\ShippingRequest\ShippingRequestLine;
use App\Entity\Statut;
use App\Entity\TrackingMovement;
use App\Entity\Transporteur;
use App\Entity\Utilisateur;
use App\Service\AttachmentService;
use App\Service\LanguageService;
use App\Exceptions\FormException;
use App\Service\ArticleDataService;
use App\Service\CSVExportService;
use App\Service\DataExportService;
use App\Service\FormService;
use App\Service\ShippingRequest\ShippingRequestExpectedLineService;
use App\Service\MouvementStockService;
use App\Service\ShippingRequest\ShippingRequestService;
use App\Service\StatusHistoryService;
use App\Service\TrackingMovementService;
use App\Service\TranslationService;
use App\Service\UniqueNumberService;
use App\Service\UserService;
use App\Service\VisibleColumnService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Google\Service\Keep\User;
use PHPUnit\Util\Json;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Annotation\Route;
use Throwable;
use WiiCommon\Helper\Stream;

#[Route("/expeditions")]
class ShippingRequestController extends AbstractController {

    #[Route("/", name: "shipping_request_index", options: ["expose" => true])]
    #[HasPermission([Menu::DEM, Action::DISPLAY_SHIPPING])]
    public function index(EntityManagerInterface $entityManager,
                          ShippingRequestService $service,
                          TranslationService $translationService): Response {
        $filtreSupRepository = $entityManager->getRepository(FiltreSup::class);

        $currentUser = $this->getUser();
        $fields = $service->getVisibleColumnsConfig($currentUser);


        $statutRepository = $entityManager->getRepository(Statut::class);
        $carrierRepository = $entityManager->getRepository(Transporteur::class);

        $dateChoice = [
            [
                'name' => 'createdAt',
                'label' => $translationService->translate('Général', null, 'Zone liste', 'Date de création'),
            ],
            [
                'name' => 'requestCaredAt',
                'label' => $translationService->translate('Demande', 'Expédition', 'Date de prise en charge souhaitée'),
            ],
            [
                'name' => 'validatedAt',
                'label' => $translationService->translate('Demande', 'Expédition', 'Date de validation'),
            ],
            [
                'name' => 'plannedAt',
                'label' => $translationService->translate('Demande', 'Expédition', 'Date de planification'),
            ],
            [
                'name' => 'expectedPickedAt',
                'label' => $translationService->translate('Demande', 'Expédition', 'Date d\'enlèvement prévu'),
            ],
            [
                'name' => 'treatedAt',
                'label' => $translationService->translate('Demande', 'Expédition', 'Date d\'expédition'),
            ],
        ];
        foreach ($dateChoice as &$choice) {
            $choice['default'] = (bool)$filtreSupRepository->findOnebyFieldAndPageAndUser('date-choice_'.$choice['name'], 'expedition', $currentUser);
        }
        if (Stream::from($dateChoice)->every(function ($choice) { return !$choice['default']; })) {
            $dateChoice[0]['default'] = true;
        }

        return $this->render('shipping_request/index.html.twig', [
            "fields" => $fields,
            "initial_visible_columns" => $this->apiColumns($service)->getContent(),
            "statuses" => $statutRepository->findByCategorieName(ShippingRequest::CATEGORIE, 'displayOrder'),
            "dateChoices" =>$dateChoice,
            "carriersForFilter" => $carrierRepository->findAll(),
        ]);
    }

    #[Route("/api-columns", name: "shipping_request_api_columns", options: ["expose" => true], methods: ['GET'], condition: "request.isXmlHttpRequest()")]
    #[HasPermission([Menu::DEM, Action::DISPLAY_SHIPPING], mode: HasPermission::IN_JSON)]
    public function apiColumns(ShippingRequestService $service): Response {
        $currentUser = $this->getUser();
        $columns = $service->getVisibleColumnsConfig($currentUser);

        return new JsonResponse($columns);
    }

    #[Route("/api", name: "shipping_request_api", options: ["expose" => true], methods: ['POST'], condition: "request.isXmlHttpRequest()")]
    #[HasPermission([Menu::DEM, Action::DISPLAY_SHIPPING], mode: HasPermission::IN_JSON)]
    public function api(Request                $request,
                        ShippingRequestService $service,
                        EntityManagerInterface $entityManager) {
        return $this->json($service->getDataForDatatable( $entityManager, $request));
    }

    #[Route("/{id}/voir", name:"shipping_request_show", options: ["expose" => true])]
    #[HasPermission([Menu::DEM, Action::DISPLAY_SHIPPING])]
    public function show(ShippingRequest                    $shippingRequest,
                         ShippingRequestExpectedLineService $expectedLineService,
                         ShippingRequestService             $shippingRequestService,
                         EntityManagerInterface             $entityManager): Response {
        return $this->render('shipping_request/show.html.twig', [
            'shipping'=> $shippingRequest,
            'detailsTransportConfig' => $shippingRequestService->createHeaderTransportDetailsConfig($shippingRequest),
            'editableExpectedLineForm' => $expectedLineService->editatableLineForm($shippingRequest),
        ]);
    }

    #[Route("/colonne-visible", name: "save_column_visible_for_shipping_request", options: ["expose" => true], methods: ['POST'], condition: "request.isXmlHttpRequest()")]
    #[HasPermission([Menu::DEM, Action::DISPLAY_SHIPPING], mode: HasPermission::IN_JSON)]
    public function saveColumnVisible(Request                $request,
                                      EntityManagerInterface $entityManager,
                                      VisibleColumnService   $visibleColumnService,
                                      TranslationService     $translationService): Response {
        $data = json_decode($request->getContent(), true);
        $fields = array_keys($data);
        $fields[] = "actions";

        /** @var Utilisateur $currentUser */
        $currentUser = $this->getUser();
        $visibleColumnService->setVisibleColumns('shippingRequest', $fields, $currentUser);

        $entityManager->flush();

        return $this->json([
            'success' => true,
            'msg' => $translationService->translate('Général', null, 'Zone liste', 'Vos préférences de colonnes à afficher ont bien été sauvegardées', false)
        ]);
    }

    #[Route("/{request}/expected-lines-api", name: "api_shipping_request_expected_lines", options: ["expose" => true], methods: "GET", condition: "request.isXmlHttpRequest()")]
    #[HasPermission([Menu::DEM, Action::DISPLAY_SHIPPING], mode: HasPermission::IN_JSON)]
    public function apiShippingRequestExpectedLines(ShippingRequest                    $request,
                                                    FormService                        $formService,
                                                    ShippingRequestExpectedLineService $expectedLineService): JsonResponse {
        $data = Stream::from($request->getExpectedLines())
            ->map(fn (ShippingRequestExpectedLine $line) => $expectedLineService->editatableLineForm($request, $line))
            ->values();

        $emptyForm = $expectedLineService->editatableLineForm($request);

        $data[] = $emptyForm;
        $data[] = $formService->editableAddRow($emptyForm);

        return $this->json([
            "data" => $data,
        ]);
    }

    #[Route("/new", name: "shipping_request_new", options: ["expose" => true], methods: ['POST'], condition: "request.isXmlHttpRequest()")]
    #[HasPermission([Menu::DEM, Action::CREATE_SHIPPING], mode: HasPermission::IN_JSON)]
    public function new(Request                $request,
                        EntityManagerInterface $entityManager,
                        ShippingRequestService $shippingRequestService,
                        UniqueNumberService    $uniqueNumberService,
                        StatusHistoryService   $statusHistoryService): JsonResponse {
        $data = $request->request;
        $now = new \DateTime('now');

        $statusRepository = $entityManager->getRepository(Statut::class);
        $shippingRequest = new ShippingRequest();
        $shippingRequest
            ->setNumber($uniqueNumberService->create($entityManager, ShippingRequest::NUMBER_PREFIX, ShippingRequest::class, UniqueNumberService::DATE_COUNTER_FORMAT_TRANSPORT))
            ->setCreatedAt($now)
            ->setCreatedBy($this->getUser());

        $statusHistoryService->updateStatus(
            $entityManager,
            $shippingRequest,
            $statusRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::SHIPPING_REQUEST, ShippingRequest::STATUS_DRAFT),
            ['setStatus' => true, 'date' => $now],
        );

        $entityManager->persist($shippingRequest);

        $success = $shippingRequestService->updateShippingRequest($entityManager, $shippingRequest, $data);
        if($success){
            $entityManager->flush();
            return $this->json([
                'success' => true,
                'msg' => 'Votre demande d\'expédition a bien été créée.',
                'shippingRequestId' => $shippingRequest->getId(),
            ]);
        } else {
            throw new FormException();
        }
    }

    #[Route("/{shippingRequest}/submit-expected-lines", name: "shipping_request_submit_changes_expected_lines", options: ["expose" => true], methods: "POST", condition: "request.isXmlHttpRequest()")]
    #[HasPermission([Menu::DEM, Action::EDIT], mode: HasPermission::IN_JSON)]
    public function submitExpectedLines(ShippingRequest                    $shippingRequest,
                                        Request                            $request,
                                        ShippingRequestExpectedLineService $expectedLineService,
                                        ShippingRequestService             $shippingRequestService,
                                        EntityManagerInterface             $entityManager): JsonResponse {
        if ($data = json_decode($request->getContent(), true)) {
            $lineId = $data['lineId'] ?? null;
            if ($lineId) {
                $lineRepository = $entityManager->getRepository(ShippingRequestExpectedLine::class);
                $line = $lineRepository->find($lineId);
                $created = false;
            }
            else if ($data['referenceArticle'] ?? null) {
                // creation
                $referenceArticleRepository = $entityManager->getRepository(ReferenceArticle::class);
                $referenceArticle = $referenceArticleRepository->find($data['referenceArticle']);

                if (!$referenceArticle) {
                    throw new FormException('Formulaire invalide');
                }

                $line = $shippingRequest->getExpectedLine($referenceArticle);
                if($line){
                    throw new FormException('La référence article a déjà été ajoutée');
                }

                $line = $expectedLineService->persist($entityManager, [
                    'referenceArticle' => $referenceArticle,
                    'request' => $shippingRequest
                ]);

                $created = true;
            }
            else {
                throw new FormException('Formulaire invalide');
            }

            $line
                ->setQuantity($data['quantity'] ?? null)
                ->setUnitPrice($data['price'] ?? null)
                ->setUnitWeight($data['weight'] ?? null);

            $shippingRequestService->updateNetWeight($shippingRequest);
            $shippingRequestService->updateTotalValue($shippingRequest);

            $entityManager->flush();

            $resp = [
                'success' => true,
                'created' => $created,
                'lineId' => $lineId ?? $line->getId(),
            ];
        }
        return new JsonResponse(
            $resp ?? ['success' => false, 'created' => false]
        );
    }

    #[Route("/expected-line/{line}", name: "shipping_request_expected_line_delete", options: ["expose" => true], methods: ["DELETE"], condition: "request.isXmlHttpRequest()")]
    #[HasPermission([Menu::DEM, Action::EDIT], mode: HasPermission::IN_JSON)]
    public function removeExpectedLine(EntityManagerInterface      $entityManager,
                                       ShippingRequestService      $shippingRequestService,
                                       ShippingRequestExpectedLine $line): Response {

        if ($line->getRequest()?->getStatus()?->getCode() === ShippingRequest::STATUS_DRAFT
            || $line->getRequest()?->getStatus()?->getCode() === ShippingRequest::STATUS_TO_TREAT) {
            $shippingRequest = $line->getRequest();
            $shippingRequest->removeExpectedLine($line);
            $entityManager->remove($line);

            $shippingRequestService->updateNetWeight($shippingRequest);
            $shippingRequestService->updateTotalValue($shippingRequest);

            $entityManager->flush();
        }

        return $this->json([
            'success' => true,
            'msg' => "La ligne a bien été retirée."
        ]);
    }

    #[Route("/edit", name: "shipping_request_edit", options: ["expose" => true], methods: ['POST'], condition: "request.isXmlHttpRequest()")]
    #[HasPermission([Menu::DEM, Action::CREATE_SHIPPING], mode: HasPermission::IN_JSON)]
    public function edit(Request                $request,
                         EntityManagerInterface $entityManager,
                         ShippingRequestService $shippingRequestService,
                         UserService            $userService,
                         TranslationService     $translationService): JsonResponse
    {
        $data = $request->request;
        $success = null;
        $user = $this->getUser();

        $shippingRequestId = $request->get('shippingRequestId');
        if ($shippingRequestId) {
            $shippingRequestRepository = $entityManager->getRepository(ShippingRequest::class);
            $shippingRequest = $shippingRequestRepository->find($shippingRequestId);
        } else {
            throw new FormException('La demande d\'expédition n\'a pas été trouvée.');
        }


        // right & status to treat
        if ($shippingRequest->isToTreat()) {
            if ($userService->hasRightFunction(Menu::DEM, Action::EDIT_TO_TREAT_SHIPPING, $user)) {
                $success = $shippingRequestService->updateShippingRequest($entityManager, $shippingRequest, $data);
            } else {
                throw new FormException("Vous n'avez pas la permission de modifier cette " . mb_strtolower($translationService->translate("Demande", "Expédition", "Demande d'expédition", false)) . " avec le statut " . $shippingRequest->getStatus()->getCode() . " ");
            }

        // right & status scheduled
        } else if ($shippingRequest->isScheduled()) {
            if ($userService->hasRightFunction(Menu::DEM, Action::EDIT_PLANIFIED_SHIPPING)) {
                $success = $shippingRequestService->updateShippingRequest($entityManager, $shippingRequest, $data);
            } else {
                throw new FormException("Vous n'avez pas la permission de modifier cette " . mb_strtolower($translationService->translate("Demande", "Expédition", "Demande d'expédition", false)) . " avec le statut " . $shippingRequest->getStatus()->getCode() . " ");
            }

        // right & status shipped
        } else if ($shippingRequest->isShipped()) {
            if ($userService->hasRightFunction(Menu::DEM, Action::EDIT_SHIPPED_SHIPPING)) {
                $success = $shippingRequestService->updateShippingRequest($entityManager, $shippingRequest, $data);
            } else {
                throw new FormException("Vous n'avez pas la permission de modifier cette " . mb_strtolower($translationService->translate("Demande", "Expédition", "Demande d'expédition", false)) . " avec le statut " . $shippingRequest->getStatus()->getCode() . " ");
            }
        } else if ($shippingRequest->isDraft()) {
            $success = $shippingRequestService->updateShippingRequest($entityManager, $shippingRequest, $data);
        }

        if ($success) {
            $entityManager->flush();
            return $this->json([
                'success' => true,
                'msg' => 'Votre demande d\'expédition a bien été enregistrée',
                'shippingRequestId' => $shippingRequest->getId(),
            ]);
        }
        // usually never call
        throw new FormException();
    }

    #[Route("/check_expected_lines_data/{id}", name: 'check_expected_lines_data', options: ["expose" => true], methods: ['GET'])]
    #[HasPermission([Menu::DEM, Action::DISPLAY_SHIPPING])]
    public function checkExpectedLinesData(ShippingRequest $shippingRequest): JsonResponse
    {

        $expectedLines = $shippingRequest->getExpectedLines();

        if ($expectedLines->count() <= 0) {
            return $this->json([
                'success' => false,
                'msg' => 'Veuillez ajouter au moins une référence.',
            ]);
        }

        $errors = [];

        /** @var ShippingRequestExpectedLine $expectedLine */
        foreach ($expectedLines as $expectedLine) {
            $referenceArticle = $expectedLine->getReferenceArticle();

            //FDS & codeOnu & classeProduct needed if it's dangerousGoods
            if ($referenceArticle->isDangerousGoods()) {

                if (!$referenceArticle->getSheet()) {
                    $errors[] = "La référence " . $referenceArticle->getReference() . " n'a pas de FDS.";
                }
                if (!$referenceArticle->getOnuCode()) {
                    $errors[] = "La référence " . $referenceArticle->getReference() . " n'a pas de code ONU.";
                }
                if (!$referenceArticle->getProductClass()) {
                    $errors[] = "La référence " . $referenceArticle->getReference() . " n'a pas de classe de produit.";
                }
            }

            //codeNdp needed if shipment is international
            if ($shippingRequest->getShipment() === ShippingRequest::SHIPMENT_INTERNATIONAL
                && !$referenceArticle->getNdpCode()) {

                $errors[] = "La référence " . $referenceArticle->getReference() . " n'a pas de code NDP.";
            }
        }

        return $this->json([
            'success' => true,
            'errors' => implode("<br>", $errors),
        ]);
    }


    #[Route("/get-format-expected-lines/{id}", name: "get_format_expected_lines", options: ["expose" => true], methods: ['GET'])]
    #[HasPermission([Menu::DEM, Action::DISPLAY_SHIPPING])]
    public function getFormatExpectedLinesForPacking (ShippingRequest          $shippingRequest,
                                                      ShippingRequestService   $shippingRequestService):JsonResponse{
        $expectedLines = $shippingRequest->getExpectedLines();

        return $this->json([
            'success' => true,
            'expectedLines' => $shippingRequestService->formatExpectedLinesForPacking($expectedLines)
        ]);
    }

    #[Route("/delete-shipping-request/{id}", name: "delete_shipping_request", options: ["expose" => true], methods: ['DELETE'])]
    #[HasPermission([Menu::DEM, Action::DISPLAY_SHIPPING])]
    public function deleteShippingRequest(ShippingRequest        $shippingRequest,
                                          EntityManagerInterface $entityManager,
                                          UserService            $userService,
                                          AttachmentService      $attachmentService,
                                          ShippingRequestService $shippingRequestService,
                                          TranslationService     $translationService): Response
    {

        $user = $this->getUser();
        $statusHistoryRepository = $entityManager->getRepository(StatusHistory::class);

        // status
        $isDraft = $shippingRequest->getStatus()->getCode() === ShippingRequest::STATUS_DRAFT;
        $isToTreat = $shippingRequest->getStatus()->getCode() === ShippingRequest::STATUS_TO_TREAT;
        $isScheduled = $shippingRequest->getStatus()->getCode() === ShippingRequest::STATUS_SCHEDULED;
        $isShipped = $shippingRequest->getStatus()->getCode() === ShippingRequest::STATUS_SHIPPED;

        // right
        $hasRightDeleteDraft = $userService->hasRightFunction(Menu::DEM, Action::DELETE, $user);
        $hasRightDeleteTreat = $userService->hasRightFunction(Menu::DEM, Action::DELETE_TO_TREAT_SHIPPING, $user);
        $hasRightDeleteScheduled = $userService->hasRightFunction(Menu::DEM, Action::DELETE_PLANIFIED_SHIPPING, $user);
        $hasRightDeleteShipped = $userService->hasRightFunction(Menu::DEM, Action::DELETE_SHIPPED_SHIPPING);

        if (($isDraft && !$hasRightDeleteDraft)
            || ($isToTreat && !$hasRightDeleteTreat)
            || ($isScheduled && !$hasRightDeleteScheduled)
            || ($isShipped && !$hasRightDeleteShipped)) {
            throw new FormException("Vous n'avez pas la permission de supprimer cette " . mb_strtolower($translationService->translate("Demande", "Expédition", "Demande d'expédition", false)) . " au statut " . $this->getFormatter()->status($shippingRequest->getStatus()));
        }

        // remove status_history
        $statusHistoryToRemove = $statusHistoryRepository->findBy(['shippingRequest' => $shippingRequest->getId()]);

        try {
            $entityManager->wrapInTransaction(function(EntityManagerInterface $entityManager) use ($shippingRequestService, $shippingRequest, $statusHistoryToRemove, $attachmentService) {
                foreach ($statusHistoryToRemove as $status) {
                    $entityManager->remove($status);
                }
                $shippingRequestService->deletePacking($entityManager, $shippingRequest);

                // remove ShippingRequesExpectedtLine
                foreach ($shippingRequest->getExpectedLines() as $expectedLine) {

                    $entityManager->remove($expectedLine);
                    $shippingRequest->removeExpectedLine($expectedLine);
                }

                foreach ($shippingRequest->getAttachments() as $attachment) {
                    $attachmentService->removeAndDeleteAttachment($attachment, $shippingRequest);
                }

                $entityManager->remove($shippingRequest);

                $entityManager->flush();
            });
        }
        catch(Throwable $throwable) {
            if (!($throwable instanceof FormException)) {
                throw new FormException("Une erreur est survenue lors de la suppression du colisage veuillez contacter le support");
            }
            else {
                throw $throwable;
            }
        }
        return $this->json(["success" => true]);
    }


    #[Route("/validate-shipping-request/{shippingRequest}", name:'shipping_request_validation', options:["expose"=>true], methods: ['GET'])]
    #[HasPermission([Menu::DEM, Action::DISPLAY_SHIPPING])]
    public function shippingRequestValidation(ShippingRequest        $shippingRequest,
                                              StatusHistoryService   $statusHistoryService,
                                              ShippingRequestService $shippingRequestService,
                                              EntityManagerInterface $entityManager,
                                              TranslationService     $translationService): JsonResponse
    {
        $currentUser = $this->getUser();

        if ($shippingRequest->getStatus()->getCode() !== ShippingRequest::STATUS_DRAFT) {
            return $this->json([
                'success' => false,
                'msg' => 'La ' . mb_strtolower($translationService->translate("Demande", "Expédition", "Demande d'expédition", false)) . ' n\'est pas en brouillon.'
            ]);
        }

        // shippingRequest need at least 1 expectedLines (ref)
        if($shippingRequest->getExpectedLines()->count() <= 0){
            return $this->json([
                'success'=>false,
                'msg'=> 'Veuillez ajouter au moins une référence.',
            ]);
        }

        $newStatusForShippingRequest = $entityManager
            ->getRepository(Statut::class)
            ->findOneByCategorieNameAndStatutCode(
                CategorieStatut::SHIPPING_REQUEST,
                ShippingRequest::STATUS_TO_TREAT
            );

        $shippingRequest
            ->setValidatedAt(new \DateTime())
            ->setValidatedBy($currentUser);

        $statusHistoryService->updateStatus(
            $entityManager,
            $shippingRequest,
            $newStatusForShippingRequest,
            ['setStatus'=> true],
        );

        // Check that the status has been updated
        if($shippingRequest->getStatus() !== $newStatusForShippingRequest){
            return $this->json([
                'success'=>false,
                'msg'=> 'Une erreur est survenue lors du changement de statut.',
            ]);
        }

        $shippingRequestService->sendMailForStatus($entityManager, $shippingRequest);

        $entityManager->flush();

        return $this->json([
            "success"=> true,
            'msg'=> 'La validation de votre ' . mb_strtolower($translationService->translate("Demande", "Expédition", "Demande d'expédition", false)) . ' a bien été prise en compte. ',
        ]);
    }

    #[Route("/get-header-config/{id}", name: "shipping_request_header_config", options: ["expose"=>true], methods: ['GET'])]
    #[HasPermission([Menu::DEM, Action::DISPLAY_SHIPPING])]
    public function getTransportHeaderConfig(ShippingRequest        $shippingRequest,
                                             ShippingRequestService $shippingRequestService): Response {
        return $this->json([
            'detailsTransportConfig' => $shippingRequestService->createHeaderTransportDetailsConfig($shippingRequest)
        ]);
    }

    #[Route("/csv", name: "get_shipping_requests_csv", options: ["expose" => true], methods: ['GET'])]
    public function exportShippingRequests(EntityManagerInterface $entityManager,
                                           CSVExportService       $csvService,
                                           DataExportService      $dataExportService) {
        $shippingRepository = $entityManager->getRepository(ShippingRequest::class);

        $csvHeader = $dataExportService->createShippingRequestHeader();

        $today = new DateTime();
        $today = $today->format("d-m-Y-H-i-s");
        $shippingRequestsIterator = $shippingRepository->iterateShippingRequests();

        return $csvService->streamResponse(function ($output) use ($dataExportService, $shippingRequestsIterator) {
            $dataExportService->exportShippingRequests($shippingRequestsIterator, $output);
        }, "export-expeditions_$today.csv", $csvHeader);
    }

    #[Route("/{shippingRequest}/status-history-api", name: "shipping_request_status_history_api", options: ['expose' => true], methods: "GET")]
    public function statusHistoryApi(ShippingRequest $shippingRequest,
                                     LanguageService $languageService): JsonResponse {
        $user = $this->getUser();
        $statusWorkflow = ShippingRequest::STATUS_WORKFLOW_SHIPPING_REQUEST;
        return $this->json([
            "success" => true,
            "template" => $this->renderView('shipping_request/status-history.html.twig', [
                "userLanguage" => $user->getLanguage(),
                "defaultLanguage" => $languageService->getDefaultLanguage(),
                "statusWorkflow" => $statusWorkflow,
                "statusesHistory" => Stream::from($shippingRequest->getStatusHistory())
                    ->map(fn(StatusHistory $statusHistory) => [
                        "status" => $this->getFormatter()->status($statusHistory->getStatus()),
                        "date" => $languageService->getCurrentUserLanguageSlug() === Language::FRENCH_SLUG
                            ? $this->getFormatter()->longDate($statusHistory->getDate(), ["short" => true, "time" => true])
                            : $this->getFormatter()->datetime($statusHistory->getDate(), "", false, $user),
                    ])
                    ->toArray(),
                "shippingRequest" => $shippingRequest,
            ]),
        ]);
    }

    #[Route(["/form/{id}", "/form"], name: "shipping_request_form", options: ["expose" => true], methods: ['GET'])]
    #[HasPermission([Menu::DEM, Action::DISPLAY_SHIPPING])]
    public function getForm(?ShippingRequest $shippingRequest): Response {
        $shippingRequest = $shippingRequest ?? new ShippingRequest();

        return $this->json([
            'success' => true,
            'html' => $this->renderView('shipping_request/form.html.twig', [
                'shipping' => $shippingRequest,
            ]),
        ]);
    }

    #[Route("/submit-packing/{id}", name: "shipping_request_submit_packing", options: ["expose" => true], methods: ['POST'])]
    #[HasPermission([Menu::DEM, Action::DISPLAY_SHIPPING])]
    public function postSubmitPacking(ShippingRequest         $shippingRequest,
                                      Request                 $request,
                                      EntityManagerInterface  $entityManager,
                                      ShippingRequestService  $shippingRequestService,
                                      ArticleDataService      $articleDataService,
                                      TrackingMovementService $trackingMovementService,
                                      MouvementStockService   $stockMovementService,
                                      StatusHistoryService    $statusHistoryService,
                                      TranslationService      $translationService,
                                      UserService             $userService): Response {
        if (!in_array($shippingRequest->getStatus()->getCode(), [ShippingRequest::STATUS_TO_TREAT, ShippingRequest::STATUS_SCHEDULED])) {
            return $this->json([
                'success' => false,
                'msg' => 'Cette' . mb_strtolower($translationService->translate("Demande", "Expédition", "Demande d'expédition", false)) . ' n\'a pas le bon statut.',
            ]);
        }

        $data = json_decode($request->getContent(), true);
        if (!count($data)) {
            throw new FormException("Une Erreur est survenue lors de la récupération des données.");
        }

        $now = new DateTime('now');

        $isEdit = $shippingRequest->getStatus()->getCode() === ShippingRequest::STATUS_SCHEDULED;
        if (($isEdit && !$userService->hasRightFunction(Menu::DEM, Action::EDIT_PLANIFIED_SHIPPING))
            || (!$isEdit && !$userService->hasRightFunction(Menu::DEM, Action::EDIT_TO_TREAT_SHIPPING))) {
            throw new FormException("Vous n'avez pas les droits pour faire cette action.");
        }

        $ShippingRequestExpectedLineRepository = $entityManager->getRepository(ShippingRequestExpectedLine::class);
        $locationRepository = $entityManager->getRepository(Emplacement::class);
        $settingRepository = $entityManager->getRepository(Setting::class);
        $statusRepository = $entityManager->getRepository(Statut::class);
        $carrierRepository = $entityManager->getRepository(Transporteur::class);
        $quantityByExpectedLine = [];

        $packLocationId = $settingRepository->getOneParamByLabel(Setting::SHIPPING_LOCATION_FROM);
        $packLocation = $packLocationId ? $locationRepository->find($packLocationId) : null;

        if (!$packLocation) {
            throw new FormException("L'emplacement d'expédition par défaut n'est pas paramétré");
        }

        if (isset($data['packing'])) {
            if ($isEdit) {
                try {
                    $entityManager->wrapInTransaction(function(EntityManagerInterface $entityManager) use ($shippingRequestService, $shippingRequest) {
                        $shippingRequestService->deletePacking($entityManager, $shippingRequest);
                        $entityManager->flush();
                    });
                }
                catch(Throwable $throwable) {
                    if (!($throwable instanceof FormException)) {
                        throw new FormException("Une erreur est survenue lors de la suppression du colisage veuillez contacter le support");
                    }
                    else {
                        throw $throwable;
                    }
                }
            }

            $generatedBarcode = [];
            foreach ($data['packing'] as $index => $pack) {
                if (!count(($pack['lines'] ?? []))) {
                    throw new FormException('Une Erreur est survenue lors de la récupération des données.');
                }

                $shippingPack = $shippingRequestService->createShippingRequestPack($entityManager, $shippingRequest, $index + 1, $pack['size'] ?? null, $packLocation, ['date' => $now]);
                $entityManager->persist($shippingPack);

                foreach($pack['lines'] as $line) {
                    if (!isset($line['lineId']) || !isset($line['quantity'])) {
                        throw new FormException();
                    }
                    $expectedLineId = $line['lineId'];
                    $pickedQuantity = $line['quantity'];

                    $requestExpectedLine = $ShippingRequestExpectedLineRepository->find($expectedLineId);
                    if (!$requestExpectedLine) {
                        throw new FormException('Une Erreur est survenue lors de la récupération des données.');
                    }

                    $referenceArticle = $requestExpectedLine->getReferenceArticle();

                    if ($referenceArticle->getTypeQuantite() === ReferenceArticle::QUANTITY_TYPE_REFERENCE) {
                        $referenceArticle->setQuantiteReservee($referenceArticle->getQuantiteReservee() + $pickedQuantity);
                        $referenceArticle->setQuantiteStock($referenceArticle->getQuantiteStock() + $pickedQuantity);
                    } else {
                        $article = $articleDataService->newArticle(
                            $entityManager,
                            [
                                'statut' => Article::STATUT_EN_TRANSIT,
                                'refArticle' => $referenceArticle,
                                'emplacement' => $packLocation,
                                'quantite' => $pickedQuantity,
                                'prix' => $requestExpectedLine->getUnitPrice(),
                                'articleFournisseur' => $requestExpectedLine->getReferenceArticle()->getArticlesFournisseur()->first()->getId(),
                                'currentLogisticUnit' => $shippingPack->getPack(),
                            ],
                            [
                                'excludeBarcodes' => $generatedBarcode,
                            ]);
                        $generatedBarcode[] = $article->getBarCode();
                    }

                    $articleOrReference = $referenceArticle->getTypeQuantite() === ReferenceArticle::QUANTITY_TYPE_REFERENCE ? $referenceArticle : $article ;

                    $stockMovement = $stockMovementService->createMouvementStock(
                        $this->getUser(),
                        null,
                        $pickedQuantity,
                        $articleOrReference,
                        MouvementStock::TYPE_ENTREE,
                        [
                            'from'=> $shippingRequest,
                            'date' => $now,
                            'locationTo' => $packLocation
                        ]
                    );
                    $entityManager->persist($stockMovement);

                    $trackingMovementDrop = $trackingMovementService->createTrackingMovement(
                        $articleOrReference->getTrackingPack() ?: $articleOrReference->getBarCode(),
                        $packLocation,
                        $this->getUser(),
                        $now,
                        false,
                        null,
                        TrackingMovement::TYPE_DEPOSE,
                        [
                            'from' => $shippingRequest,
                            'shippingRequest' => $shippingRequest,
                            'refOrArticle' => $articleOrReference,
                            'mouvementStock' => $stockMovement,
                            'logisticUnitParent' => $shippingPack->getPack(),
                            "quantity" => $pickedQuantity,
                        ]
                    );
                    $entityManager->persist($trackingMovementDrop);

                    if(isset($article)) {
                        $trackingMovementDropLogisticUnit = $trackingMovementService->createTrackingMovement(
                            $trackingMovementDrop->getPack(),
                            $packLocation,
                            $this->getUser(),
                            $now,
                            false,
                            null,
                            TrackingMovement::TYPE_DROP_LU,
                            [
                                'from'=>$shippingRequest,
                                'shippingRequest'=> $shippingRequest,
                                'refOrArticle' => $article,
                                'mouvementStock' => $stockMovement,
                                'logisticUnitParent' => $shippingPack->getPack(),
                                "quantity" => $pickedQuantity,
                            ]
                        );
                        $entityManager->persist($trackingMovementDropLogisticUnit);
                        $trackingMovementDrop->setMainMovement($trackingMovementDropLogisticUnit);
                    }

                    $requestLine = new ShippingRequestLine();
                    $requestLine
                        ->setQuantity($pickedQuantity)
                        ->setArticleOrReference($articleOrReference)
                        ->setShippingPack($shippingPack)
                        ->setExpectedLine($requestExpectedLine);

                    $entityManager->persist($requestLine);
                    $quantityByExpectedLine[$expectedLineId] = ($quantityByExpectedLine[$expectedLineId] ?? 0) + $pickedQuantity;
                }
            }

            $quantityError = Stream::from($shippingRequest->getExpectedLines())
                ->some(fn(ShippingRequestExpectedLine $expectedLine) => ($quantityByExpectedLine[$expectedLine->getId()] ?? 0) !== $expectedLine->getQuantity());
            if ($quantityError) {
                throw new FormException('Une Erreur est survenue lors du traitement des données.');
            }
        }

        $statusHistoryService->updateStatus(
            $entityManager,
            $shippingRequest,
            $statusRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::SHIPPING_REQUEST, ShippingRequest::STATUS_SCHEDULED),
            [
                'setStatus' => true,
                'date' => $now,
                'forceCreation' => false,
            ],
        );

        $scheduleData = $data['scheduleData'] ?? [];
        $shippingRequest
            ->setPlannedBy($this->getUser())
            ->setPlannedAt($now)
            ->setGrossWeight($scheduleData['grossWeight'] ?? null)
            ->setCarrier(isset($scheduleData['carrier']) ? $carrierRepository->find($scheduleData['carrier']) : null)
            ->setTrackingNumber($scheduleData['trackingNumber'] ?? null)
            ->setExpectedPickedAt(isset($scheduleData['expectedPicketAt']) ? new DateTime($scheduleData['expectedPicketAt']) : null);

        $entityManager->flush();

        return $this->json([
            'success' => true,
        ]);
    }

    #[Route("/details/{id}", name: "shipping_request_get_details", options: ["expose" => true], methods: ['GET'])]
    #[HasPermission([Menu::DEM, Action::DISPLAY_SHIPPING])]
    public function getDetails(ShippingRequest                    $shippingRequest,
                               ShippingRequestService             $shippingRequestService,
                               ShippingRequestExpectedLineService $shippingRequestExpectedLineService,
                               EntityManagerInterface             $entityManager,
                               UserService $userService): Response {
        $natureRepository = $entityManager->getRepository(Nature::class);
        switch (strtolower($shippingRequest->getStatus()->getCode())) {
            case strtolower(ShippingRequest::STATUS_DRAFT):
                $html = $this->renderView('shipping_request/details/draft.html.twig', [
                    'shippingRequest' => $shippingRequest,
                ]);
                break;
            case strtolower(ShippingRequest::STATUS_TO_TREAT):
                if($userService->hasRightFunction(Menu::DEM, Action::EDIT_TO_TREAT_SHIPPING)){
                    $packingPackNature = $natureRepository->findOneBy(['defaultNature' => true]);

                    $html = $this->renderView('shipping_request/details/draft.html.twig', [
                        'shippingRequest' => $shippingRequest,
                        'packingPackNature' => $packingPackNature,
                    ]);
                } else {
                    $packingPackNature = $natureRepository->findOneBy(['defaultNature' => true]);

                    $html = $this->renderView('shipping_request/details/to_treat.html.twig', [
                        'shippingRequest' => $shippingRequest,
                        'expectedLines' => $shippingRequestExpectedLineService->getDataForDetailsTable($shippingRequest),
                        'packingPackNature' => $packingPackNature,
                    ]);
                }
                break;
            case strtolower(ShippingRequest::STATUS_SCHEDULED):
            case strtolower(ShippingRequest::STATUS_SHIPPED):
                $packingPackNature = $natureRepository->findOneBy(['defaultNature' => true]);
                $html = $this->renderView('shipping_request/details/scheduled.html.twig', [
                    'shippingRequest' => $shippingRequest,
                    'packingPackNature' => $packingPackNature,
                    'lines' => $shippingRequestService->getDataForScheduledRequest($shippingRequest),
                ]);
                break;
            default:
                throw new Exception('Une erreur est survenue lors de la récupération des données.');
        }

        return new JsonResponse([
            'success' => true,
            'html' => $html,
        ]);
    }

    #[Route("/treat-shipping-request-shipping/{shippingRequest}", name: "treat_shipping_request", options: ["expose" => true])]
    #[HasPermission([Menu::DEM, Action::DISPLAY_SHIPPING])]
    public function treatShippingRequest(ShippingRequest         $shippingRequest,
                                         StatusHistoryService    $statusHistoryService,
                                         EntityManagerInterface  $entityManager,
                                         MouvementStockService   $mouvementStockService,
                                         TrackingMovementService $trackingMovementService,
                                         ShippingRequestService  $shippingRequestService,
                                         TranslationService $translationService): JsonResponse
    {
        if ($shippingRequest->getStatus()->getCode() !== ShippingRequest::STATUS_SCHEDULED) {
            return $this->json([
                'success' => false,
                'msg' => 'Cette ' . mb_strtolower($translationService->translate("Demande", "Expédition", "Demande d'expédition", false)) . 'n\'a pas le statut "' . ShippingRequest::STATUS_SCHEDULED . '".',
            ]);
        }

        $user = $this->getUser();
        $dateNow = new DateTime('now');

        // repository
        $statusRepository = $entityManager->getRepository(Statut::class);
        $settingRepository = $entityManager->getRepository(Setting::class);
        $emplacementRepository = $entityManager->getRepository(Emplacement::class);

        // location
        $shippingLocationFromId = $settingRepository->getOneParamByLabel(Setting::SHIPPING_LOCATION_FROM);
        $shippingLocationFrom = $emplacementRepository->findOneBy(['id' => $shippingLocationFromId]);
        $shippingLocationToId = $settingRepository->getOneParamByLabel(Setting::SHIPPING_LOCATION_TO);
        $shippingLocationTo = $emplacementRepository->findOneBy(['id' => $shippingLocationToId]);

        // new status
        $shippedStatus = $statusRepository->findOneByCategorieNameAndStatutCode(
            CategorieStatut::SHIPPING_REQUEST,
            ShippingRequest::STATUS_SHIPPED
        );

        $consumeStatusForArticles = $statusRepository->findOneByCategorieNameAndStatutCode(
            CategorieStatut::ARTICLE,
            Article::STATUT_INACTIF
        );

        // block process if paramètre "Emplacements par défaut" vide
        if (!$shippingLocationFromId || !$shippingLocationToId) {
            return $this->json([
                'success' => false,
                'msg' => 'Veuillez remplir la section "Emplacements par défaut" dans le paramétrage des expéditions.',
            ]);
        }

        $shippingRequest
            ->setTreatedAt($dateNow)
            ->setTreatedBy($user);

        // update status & create mvt stock / track
        if ($shippingRequest->getStatus()->getCode() === $shippingRequest::STATUS_SCHEDULED) {
            $statusHistoryService->updateStatus(
                $entityManager,
                $shippingRequest,
                $shippedStatus,
                ['setStatus' => true]
            );

            /** @var ShippingRequestPack $packLines */
            foreach ($shippingRequest->getPackLines() as $packLines) {
                $logisticUnitParent = $packLines->getPack();

                // mvt prise UL
                $trackingMovement = $trackingMovementService->createTrackingMovement(
                    $logisticUnitParent,
                    $logisticUnitParent->getLastDrop()->getEmplacement(),
                    $user,
                    $dateNow,
                    false,
                    false,
                    TrackingMovement::TYPE_PRISE,
                    [
                        'from' => $shippingRequest,
                        'shippingRequest' => $shippingRequest
                    ]
                );
                $entityManager->persist($trackingMovement);

                // mvt depose UL
                $trackingMovement = $trackingMovementService->createTrackingMovement(
                    $logisticUnitParent,
                    $shippingLocationTo,
                    $user,
                    $dateNow,
                    false,
                    false,
                    TrackingMovement::TYPE_DEPOSE,
                    [
                        'from' => $shippingRequest,
                    ]
                );
                $entityManager->persist($trackingMovement);

                /** @var ShippingRequestLine $shippingRequestLine */
                foreach ($packLines->getLines() as $shippingRequestLine) {
                    $articleOrReference = $shippingRequestLine->getArticleOrReference();

                    $newMouvementStock = $mouvementStockService->createMouvementStock(
                        $user,
                        $shippingLocationFrom,
                        $shippingRequestLine->getQuantity(),
                        $articleOrReference,
                        MouvementStock::TYPE_SORTIE,
                        [
                            'from'=> $shippingRequest,
                            "locationTo" => $shippingLocationTo,
                            'date' => $dateNow
                        ]
                    );
                    $entityManager->persist($newMouvementStock);

                    // mvt prise article
                    $trackingMovement = $trackingMovementService->createTrackingMovement(
                        $articleOrReference->getTrackingPack() ?: $articleOrReference->getBarCode(),
                        $shippingLocationFrom,
                        $user,
                        $dateNow,
                        false,
                        false,
                        TrackingMovement::TYPE_PRISE,
                        [
                            'from' => $shippingRequest,
                            'mouvementStock' => $newMouvementStock,
                            "quantity" => $shippingRequestLine->getQuantity(),
                            "logisticUnitParent" => $logisticUnitParent,
                        ]
                    );
                    $entityManager->persist($trackingMovement);

                    // mvt depose article
                    $trackingMovement = $trackingMovementService->createTrackingMovement(
                        $articleOrReference->getTrackingPack() ?: $articleOrReference->getBarCode(),
                        $shippingLocationTo,
                        $user,
                        $dateNow,
                        false,
                        false,
                        TrackingMovement::TYPE_DEPOSE,
                        [
                            'from' => $shippingRequest,
                            'mouvementStock' => $newMouvementStock,
                            "quantity" => $shippingRequestLine->getQuantity(),
                            "logisticUnitParent" => $logisticUnitParent,
                        ]
                    );
                    $entityManager->persist($trackingMovement);

                    // rmv qte & mvt sortie stock
                    if ($articleOrReference instanceof ReferenceArticle) {
                        $newStockQuantity = $articleOrReference->getQuantiteStock() - $shippingRequestLine->getQuantity();
                        $newReservedQuantity = $articleOrReference->getQuantiteReservee() - $shippingRequestLine->getQuantity();
                        $articleOrReference
                            ->setQuantiteStock($newStockQuantity)
                            ->setQuantiteReservee($newReservedQuantity);
                    }
                    else { // if ($articleOrReference instanceof Article)
                        $articleOrReference->setStatut($consumeStatusForArticles);
                    }
                }
            }
            // Check that the status has been updated
            if ($shippingRequest->getStatus() !== $shippedStatus) {
                return $this->json([
                    'success' => false,
                    'msg' => 'Une erreur est survenue lors du changement de statut.',
                ]);
            }
        }

        $shippingRequestService->sendMailForStatus($entityManager, $shippingRequest);
        $entityManager->flush();

        return $this->json([
            'success' => true,
            'msg' => "La ".mb_strtolower($translationService->translate("Demande", "Expédition", "Demande d'expédition", false))." a été expédiée."
        ]);
    }

    #[Route("/{shippingRequest}/delivery-slip", name: "post_delivery_slip", options: ["expose"=>true], methods: ['POST'], condition: "request.isXmlHttpRequest()")]
    public function postDeliverySlip(EntityManagerInterface $entityManager,
                                     ShippingRequest        $shippingRequest,
                                     ShippingRequestService $shippingRequestService): JsonResponse {
        $deliverySlipAttachment = $shippingRequestService->persistNewDeliverySlipAttachment($entityManager, $shippingRequest);
        $entityManager->flush();

        return new JsonResponse([
            'success' => true,
            'msg' => 'Le téléchargement de votre bordereau de livraison va commencer...',
            'attachmentId' => $deliverySlipAttachment->getId(),
        ]);
    }


    #[Route("/{shippingRequest}/delivery-slip/{attachment}", name:"print_delivery_slip", options:["expose"=>true], methods:['GET'], condition: "request.isXmlHttpRequest()")]
    public function printDeliverySlip(Attachment      $attachment,
                                      KernelInterface $kernel): Response {

        $response = new BinaryFileResponse(($kernel->getProjectDir() . '/public/uploads/attachements/' . $attachment->getFileName()));
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT,$attachment->getOriginalName());

        return $response;
    }
}
