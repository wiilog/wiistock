<?php

namespace App\Controller\ProductionRequest;

use App\Annotation\HasPermission;
use App\Controller\AbstractController;
use App\Controller\FieldModesController;
use App\Entity\Action;
use App\Entity\CategorieCL;
use App\Entity\CategorieStatut;
use App\Entity\Fields\FixedFieldByType;
use App\Entity\Fields\FixedFieldEnum;
use App\Entity\Fields\FixedFieldStandard;
use App\Entity\FiltreSup;
use App\Entity\FreeField\FreeField;
use App\Entity\Language;
use App\Entity\Menu;
use App\Entity\OperationHistory\ProductionHistoryRecord;
use App\Entity\ProductionRequest;
use App\Entity\StatusHistory;
use App\Entity\Statut;
use App\Entity\Type\CategoryType;
use App\Entity\Type\Type;
use App\Entity\Utilisateur;
use App\Exceptions\FormException;
use App\Helper\LanguageHelper;
use App\Service\AttachmentService;
use App\Service\CSVExportService;
use App\Service\FixedFieldService;
use App\Service\FreeFieldService;
use App\Service\LanguageService;
use App\Service\OperationHistoryService;
use App\Service\ProductionRequest\ProductionRequestService;
use App\Service\SettingsService;
use App\Service\StatusService;
use App\Service\UserService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\InputBag;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Throwable;
use WiiCommon\Helper\Stream;

#[Route('/production', name: 'production_request_')]
class ProductionRequestController extends AbstractController
{
    #[Route('/index', name: 'index', methods: [self::GET])]
    #[HasPermission([Menu::PRODUCTION, Action::DISPLAY_PRODUCTION_REQUEST])]
    public function index(Request                  $request,
                          EntityManagerInterface   $entityManager,
                          SettingsService          $settingsService,
                          ProductionRequestService $productionRequestService,
                          StatusService            $statusService): Response {
        $fixedFieldByTypeRepository = $entityManager->getRepository(FixedFieldByType::class);

        /** @var Utilisateur $currentUser */
        $currentUser = $this->getUser();
        $fields = $productionRequestService->getVisibleColumnsConfig($entityManager, $currentUser, FieldModesController::PAGE_PRODUCTION_REQUEST_LIST);

        // repository
        $statutRepository = $entityManager->getRepository(Statut::class);
        $typeRepository = $entityManager->getRepository(Type::class);
        $filterSupRepository = $entityManager->getRepository(FiltreSup::class);

        // data from request
        $query = $request->query;
        $typesFilter = $query->has('types') ? $query->all('types', '') : [];
        $statusesFilter = $query->has('statuses') ? $query->all('statuses', '') : [];
        $fromDashboard = $query->has('fromDashboard') ? $query->get('fromDashboard') : '' ;

        // case type filter selected
        if (!empty($typesFilter)) {
            $typesFilter = Stream::from($typeRepository->findBy(['id' => $typesFilter]))
                ->filterMap(fn(Type $type) => $type->getLabelIn($currentUser->getLanguage()))
                ->toArray();
        }

        // case status filter selected
        if (!empty($statusesFilter)) {
            $statusesFilter = Stream::from($statutRepository->findBy(['id' => $statusesFilter]))
                ->map(fn(Statut $status) => $status->getId())
                ->toArray();
        }

        $types = $typeRepository->findByCategoryLabels([CategoryType::PRODUCTION]);
        $attachmentAssigned = (bool)$filterSupRepository->findOnebyFieldAndPageAndUser("attachmentsAssigned", 'production', $currentUser);

        $dateChoices = FiltreSup::DATE_CHOICE_VALUES[ProductionRequest::class];

        $expectedAtSettings = $settingsService->getMinDateByTypesSettings(
            $entityManager,
            FixedFieldStandard::ENTITY_CODE_PRODUCTION,
            FixedFieldEnum::expectedAt,
            new DateTime()
        );

        return $this->render('production_request/index.html.twig', [
            "productionRequest" => new ProductionRequest(),
            "fieldsParam" => $fixedFieldByTypeRepository->getByEntity(FixedFieldStandard::ENTITY_CODE_PRODUCTION, [FixedFieldByType::ATTRIBUTE_REQUIRED_CREATE, FixedFieldByType::ATTRIBUTE_DISPLAYED_CREATE]),
            "emergencies" => $fixedFieldByTypeRepository->getElements(FixedFieldStandard::ENTITY_CODE_PRODUCTION, FixedFieldStandard::FIELD_CODE_EMERGENCY),
            "fields" => $fields,
            "initial_visible_columns" => $this->apiColumns($productionRequestService, $entityManager, $request)->getContent(),
            "dateChoices" => $dateChoices,
            "types" => $types,
            "statusStateValues" => Stream::from($statusService->getStatusStatesValues())
                ->keymap(static fn($item) => [
                    $item['id'],
                    $item['label']
                ])
                ->toArray(),
            "typesFilter" => $typesFilter,
            "statusFilter" => $statusesFilter,
            "fromDashboard" => $fromDashboard,
            "statuses" => $statutRepository->findByCategorieName(CategorieStatut::PRODUCTION, 'displayOrder'),
            "attachmentAssigned" => $attachmentAssigned,
            "expectedAtSettings" => $expectedAtSettings,
        ]);
    }

    #[Route("/api-columns", name: "api_columns", options: ["expose" => true], methods: [self::GET], condition: "request.isXmlHttpRequest()")]
    #[HasPermission([Menu::PRODUCTION, Action::DISPLAY_PRODUCTION_REQUEST])]
    public function apiColumns(ProductionRequestService $service,
                               EntityManagerInterface $entityManager,
                               Request $request): Response {
        /** @var Utilisateur $currentUser */
        $currentUser = $this->getUser();
        $dispatchMode = $request->query->getBoolean('dispatchMode');

        $columns = $service->getVisibleColumnsConfig($entityManager, $currentUser, FieldModesController::PAGE_PRODUCTION_REQUEST_LIST, false, $dispatchMode);

        return new JsonResponse($columns);
    }

    #[Route("/api", name: "api", options: ["expose" => true], methods: [self::POST], condition: "request.isXmlHttpRequest()")]
    #[HasPermission([Menu::PRODUCTION, Action::DISPLAY_PRODUCTION_REQUEST])]
    public function api(Request                  $request,
                        ProductionRequestService $productionRequestService,
                        EntityManagerInterface   $entityManager): Response {
        return $this->json($productionRequestService->getDataForDatatable($entityManager, $request));
    }

    #[Route("/voir/{id}", name: "show", options: ["expose" => true])]
    #[HasPermission([Menu::PRODUCTION, Action::DISPLAY_PRODUCTION_REQUEST])]
    public function show(EntityManagerInterface   $entityManager,
                         Request                  $request,
                         ProductionRequest        $productionRequest,
                         SettingsService          $settingsService,
                         ProductionRequestService $productionRequestService): Response {
        $fixedFieldByTypeRepository = $entityManager->getRepository(FixedFieldByType::class);
        $freeFieldRepository = $entityManager->getRepository(FreeField::class);

        $productionType = $productionRequest->getType();

        $freeFields = $freeFieldRepository->findByTypeAndCategorieCLLabel($productionType, CategorieCL::PRODUCTION_REQUEST);

        $fieldsParam = Stream::from($fixedFieldByTypeRepository->findBy([
            "entityCode" => FixedFieldStandard::ENTITY_CODE_PRODUCTION
        ]))
            ->keymap(static fn(FixedFieldByType $field) => [$field->getFieldCode(), [
                FixedFieldByType::ATTRIBUTE_DISPLAYED_EDIT => $field->isDisplayedEdit($productionType),
                FixedFieldByType::ATTRIBUTE_REQUIRED_EDIT => $field->isRequiredEdit($productionType),
            ]])
            ->toArray();

        $openModal = $request->query->get('open-modal');

        $expectedAtSettings = $settingsService->getMinDateByTypesSettings(
            $entityManager,
            FixedFieldStandard::ENTITY_CODE_PRODUCTION,
            FixedFieldEnum::expectedAt,
            $productionRequest->getCreatedAt()
        );

        $currentExpectedAtSetting = $expectedAtSettings[$productionRequest->getType()->getId()]
            ?? $expectedAtSettings["all"]
            ?? null;

        return $this->render("production_request/show/index.html.twig", [
            "fieldsParam" => $fieldsParam,
            "emergencies" => $fixedFieldByTypeRepository->getElements(FixedFieldStandard::ENTITY_CODE_PRODUCTION, FixedFieldStandard::FIELD_CODE_EMERGENCY),
            "productionRequest" => $productionRequest,
            "hasRightDeleteProductionRequest" => $productionRequestService->hasRightToDelete($productionRequest),
            "hasRightEditProductionRequest" => $productionRequestService->hasRightToEdit($productionRequest),
            "detailsConfig" => $productionRequestService->createHeaderDetailsConfig($productionRequest),
            "attachments" => $productionRequest->getAttachments(),
            "freeFields" => $freeFields,
            "openModal" => $openModal,
            "expectedAtSettings" => [
                $productionRequest->getType()->getId() => $currentExpectedAtSetting
            ],
        ]);
    }

    #[Route('/new', name: 'new', options: ['expose' => true], methods: self::POST, condition: 'request.isXmlHttpRequest()')]
    #[HasPermission([Menu::PRODUCTION, Action::CREATE_PRODUCTION_REQUEST])]
    public function new(EntityManagerInterface   $entityManager,
                        Request                  $request,
                        UserService              $userService,
                        ProductionRequestService $productionRequestService,
                        FixedFieldService        $fieldsParamService): JsonResponse {

        $post = $request->request;

        $typeRepository = $entityManager->getRepository(Type::class);
        $typeValue = FixedFieldEnum::type->name;
        $type = $typeRepository->find($post->getInt($typeValue));
        $data = $fieldsParamService->checkForErrors($entityManager, $request->request, FixedFieldStandard::ENTITY_CODE_PRODUCTION, true, null, $type);

        $quantityToGenerate = $data->getInt('quantityToGenerate');
        if ($quantityToGenerate !== 1 && !$userService->hasRightFunction(Menu::PRODUCTION, Action::DUPLICATE_PRODUCTION_REQUEST)) {
            throw new FormException("Vous n'avez pas les droits pour générer plusieurs demandes de production.");
        }

        if ($quantityToGenerate < 1) {
            throw new FormException("La quantité à générer doit être supérieure à 0.");
        }
        $limitQuantity = 10;
        if ($quantityToGenerate > $limitQuantity) {
            throw new FormException("La quantité à générer ne peut pas dépasser $limitQuantity.");
        }

        $productionRequests = [];
        $needModalConfirmationForGenerateDispatch = false;
        for ($i = 0; $i < $quantityToGenerate; $i++) {
            ['productionRequest' => $productionRequest] = $productionRequestService->updateProductionRequest($entityManager, new ProductionRequest(), $this->getUser(), $data, $request->files);
            $productionRequests[] = $productionRequest;
            $entityManager->persist($productionRequest);

            if ($productionRequestService->checkNeedModalConfirmationForGenerateDispatch($productionRequest, $userService)) {
                $needModalConfirmationForGenerateDispatch = true;
            }
        }

        $entityManager->flush();

        // can only have one production request because duplication doesn't work with this parameter (for now)
        if ($needModalConfirmationForGenerateDispatch && count($productionRequests) === 1) {
            return $this->json([
                "success" => true,
                "needModalConfirmationForGenerateDispatch" => true,
                'msg' => "Votre demande de production a bien été créée.",
                'productionRequestId' => $productionRequests[0]->getId(),
            ]);
        }

        foreach ($productionRequests as $productionRequest) {
            $productionRequestService->sendUpdateStatusEmail($productionRequest);
        }

        return $this->json([
            'success' => true,
            'msg' => "Votre demande de production a bien été créée."
        ]);
    }

    #[Route('/delete/{productionRequest}', name: 'delete', options: ['expose' => true], methods: self::DELETE, condition: 'request.isXmlHttpRequest()')]
    public function delete(ProductionRequest      $productionRequest,
                           EntityManagerInterface $entityManager,
                           AttachmentService      $attachmentService,
                           UserService            $userService): JsonResponse {

        $status = $productionRequest->getStatus();
        if (
            ($status->isNotTreated() && !$userService->hasRightFunction(Menu::PRODUCTION, Action::DELETE_TO_TREAT_PRODUCTION_REQUEST))
            || ($status->isInProgress() && !$userService->hasRightFunction(Menu::PRODUCTION, Action::DELETE_IN_PROGRESS_PRODUCTION_REQUEST))
            || ($status->isPartial() && !$userService->hasRightFunction(Menu::PRODUCTION, Action::DELETE_PARTIAL_PRODUCTION_REQUEST))
            || ($status->isTreated() && !$userService->hasRightFunction(Menu::PRODUCTION, Action::DELETE_TREATED_PRODUCTION_REQUEST))
        ) {
            throw new FormException("Accès refusé");
        }

        if(!($productionRequest->getTrackingMovements())->isEmpty()){
            return $this->json([
                "success" => false,
                "msg" => "Erreur : Cette demande est liée à un ou plusieurs mouvements de traçabilité.
                Vous devez supprimer les mouvements de traçabilité, puis supprimer la demande",
            ]);
        }

        $attachmentService->removeAttachments($entityManager, $productionRequest);

        $entityManager->remove($productionRequest);

        $entityManager->flush();

        return $this->json([
            "success" => true,
            "msg" => "La demande de production a bien été supprimée.",
            "redirect" => $this->generateUrl('production_request_index'),
        ]);
    }

    #[Route("/{id}/status-history-api", name: "status_history_api", options: ['expose' => true], methods: self::GET)]
    public function statusHistoryApi(ProductionRequest $productionRequest,
                                     LanguageService   $languageService): JsonResponse {
        $user = $this->getUser();
        return $this->json([
            "success" => true,
            "template" => $this->renderView('production_request/show/status-history.html.twig', [
                "userLanguage" => $user->getLanguage(),
                "defaultLanguage" => $languageService->getDefaultLanguage(),
                "statusesHistory" => Stream::from($productionRequest->getStatusHistory())
                    ->map(fn(StatusHistory $statusHistory) => [
                        "status" => $this->getFormatter()->status($statusHistory->getStatus()),
                        "date" => $languageService->getCurrentUserLanguageSlug() === Language::FRENCH_SLUG
                            ? $this->getFormatter()->longDate($statusHistory->getDate(), ["short" => true, "time" => true])
                            : $this->getFormatter()->datetime($statusHistory->getDate(), "", false, $user),
                        "user" => $this->getFormatter()->user($statusHistory->getInitiatedBy()),
                    ])
                    ->toArray(),
                "productionRequest" => $productionRequest,
            ]),
        ]);
    }

    #[Route("/{id}/operation-history-api", name: "operation_history_api", options: ['expose' => true], methods: self::GET)]
    public function productionHistoryApi(ProductionRequest       $productionRequest,
                                         OperationHistoryService $operationHistoryService): JsonResponse {
        return $this->json([
            "success" => true,
            "template" => $this->renderView("production_request/show/production-history.html.twig", [
                "entity" => $productionRequest,
                "history" => Stream::from($productionRequest->getHistory())
                    ->sort(static fn(ProductionHistoryRecord $h1, ProductionHistoryRecord $h2) => (
                        ($h2->getDate() <=> $h1->getDate())
                        ?: ($h2->getId() <=> $h1->getId())
                    ))
                    ->map(static fn(ProductionHistoryRecord $productionHistory) => [
                        "record" => $productionHistory,
                        "icon" => $operationHistoryService->getIconFromType($productionHistory->getRequest(), $productionHistory->getType()),
                    ])
                    ->toArray()
            ]),
        ]);
    }

    #[Route('/{productionRequest}/edit', name: 'edit', options: ['expose' => true], methods: self::POST, condition: 'request.isXmlHttpRequest()')]
    public function edit(EntityManagerInterface   $entityManager,
                         Request                  $request,
                         ProductionRequestService $productionRequestService,
                         ProductionRequest        $productionRequest,
                         UserService              $userService,
                         FixedFieldService        $fieldsParamService): JsonResponse {

        $productionRequestService->checkRoleForEdition($productionRequest);

        $currentUser = $this->getUser();
        $data = $fieldsParamService->checkForErrors($entityManager, $request->request, FixedFieldStandard::ENTITY_CODE_PRODUCTION, false);

        $oldStatus = $productionRequest->getStatus();
        $newStatus = $data->getInt(FixedFieldEnum::status->name);

        if ($newStatus !== $oldStatus->getId() && $oldStatus->getState() === Statut::TREATED) {
            throw new FormException("Vous ne pouvez pas modifier le statut de la demande de production car elle est déjà traitée.");
        }

        $productionRequestService->updateProductionRequest($entityManager, $productionRequest, $currentUser, $data, $request->files);

        $entityManager->flush();

        if($oldStatus->getId() !== $productionRequest->getStatus()->getId()) {
            $productionRequestService->sendUpdateStatusEmail($productionRequest);
        }

        if ($productionRequestService->checkNeedModalConfirmationForGenerateDispatch($productionRequest, $userService)) {
            return $this->json([
                "success" => true,
                "needModalConfirmationForGenerateDispatch" => true,
                "msg" => "La demande de production a été modifiée avec succès.",
            ]);
        }

        return $this->json([
            'success' => true,
            'msg' => "Votre demande de production a bien été modifiée."
        ]);
    }

    #[Route("/export", name: "export", options: ["expose" => true], methods: self::GET, condition: "request.isXmlHttpRequest()")]
    #[HasPermission([Menu::PRODUCTION, Action::EXPORT_PRODUCTION_REQUEST])]
    public function export(EntityManagerInterface    $entityManager,
                           Request                   $request,
                           CSVExportService          $CSVExportService,
                           ProductionRequestService  $productionRequestService,
                           LanguageService           $languageService,
                           FreeFieldService          $freeFieldService): Response {

        $filters = $request->query;

        $dateMin = $filters->get("dateMin");
        $dateMax = $filters->get("dateMax");
        $user = $this->getUser();
        $userDateFormat = $user->getDateFormat() ?: Language::DMY_FORMAT;

        try {
            $dateTimeMin = DateTime::createFromFormat("Y-m-d H:i:s", "$dateMin 00:00:00");
            $dateTimeMax = DateTime::createFromFormat("Y-m-d H:i:s", "$dateMax 23:59:59");
        } catch (Throwable) {
            return $this->json([
                "success" => false,
                "msg" => "Les dates renseignées sont invalides.",
            ]);
        }

        if ($dateTimeMin && $dateTimeMax) {
            $today = (new DateTime('now'))->format("d-m-Y-H-i");

            $defaultSlug = LanguageHelper::clearLanguage($languageService->getDefaultSlug());
            $defaultLanguage = $entityManager->getRepository(Language::class)->findOneBy(["slug" => $defaultSlug]);
            $headers = Stream::from($productionRequestService->getVisibleColumnsConfig($entityManager, $user,FieldModesController::PAGE_PRODUCTION_REQUEST_LIST, true))
                ->map(static fn(array $column) => $column["title"])
                ->toArray();

            return $CSVExportService->streamResponse(function ($output) use ($entityManager,
                                                                             $CSVExportService,
                                                                             $dateTimeMin,
                                                                             $dateTimeMax,
                                                                             $productionRequestService,
                                                                             $filters,
                                                                             $user,
                                                                             $defaultLanguage,
                                                                             $userDateFormat,
                                                                             $freeFieldService
            ) {
                $productionRequests = $entityManager->getRepository(ProductionRequest::class)->getByDates($dateTimeMin, $dateTimeMax, $filters, [
                    "userDateFormat" => $userDateFormat,
                    "language" => $user->getLanguage(),
                    "defaultLanguage" => $defaultLanguage,
                ]);

                $freeFieldsConfig = $freeFieldService->createExportArrayConfig($entityManager, [CategorieCL::PRODUCTION_REQUEST]);
                $freeFieldsById = Stream::from($productionRequests)
                    ->keymap(static fn($productionRequest) => [
                        $productionRequest['id'],
                        $productionRequest['freeFields']
                    ])
                    ->toArray();

                foreach ($productionRequests as $productionRequest) {
                    $productionRequestService->productionRequestPutLine($output, $productionRequest, $freeFieldsConfig, $freeFieldsById);
                }
            }, "production_$today.csv", $headers);
        } else {
            throw new BadRequestHttpException();
        }
    }

    #[Route("/{productionRequest}/update-status-content", name: "update_status_content", options: ["expose" => true], methods: [self::GET])]
    public function productionRequestUpdateStatusContent(EntityManagerInterface $entityManager,
                                                         ProductionRequest      $productionRequest): JsonResponse {

        $fixedFieldByTypeRepository = $entityManager->getRepository(FixedFieldByType::class);
        $productionType = $productionRequest->getType();

        $fieldsParam = Stream::from($fixedFieldByTypeRepository->findBy([
            "entityCode" => FixedFieldStandard::ENTITY_CODE_PRODUCTION
        ]))
            ->keymap(static fn(FixedFieldByType $field) => [$field->getFieldCode(), [
                FixedFieldByType::ATTRIBUTE_DISPLAYED_EDIT => $field->isDisplayedEdit($productionType),
                FixedFieldByType::ATTRIBUTE_REQUIRED_EDIT => $field->isRequiredEdit($productionType),
            ]])
            ->toArray();

        $html = $this->renderView('production_request/planning/update-status-form.html.twig', [
            "productionRequest" => $productionRequest,
            "fieldsParam" => $fieldsParam,
        ]);

        return $this->json([
            "success" => true,
            "html" => $html
        ]);
    }

    #[Route("/{productionRequest}/update-status", name: "update_status", options: ["expose" => true], methods: self::POST)]
    public function updateStatus(EntityManagerInterface   $entityManager,
                                 ProductionRequest        $productionRequest,
                                 Request                  $request,
                                 UserService              $userService,
                                 ProductionRequestService $productionRequestService): JsonResponse {

        $productionRequestService->checkRoleForEdition($productionRequest);

        $currentUser = $this->getUser();

        $inputBag = new InputBag([
            FixedFieldEnum::status->name => $request->request->get(FixedFieldEnum::status->name),
            FixedFieldEnum::comment->name => $request->request->get(FixedFieldEnum::comment->name),
            'files' => $request->request->has('savedFiles')
                ? $request->request->all('savedFiles')
                : [],
        ]);

        $oldStatus = $productionRequest->getStatus();

        if ($oldStatus->getState() === Statut::TREATED) {
            throw new FormException("Vous ne pouvez pas modifier le statut de la demande de production car elle est déjà traitée.");
        }

        $data = $productionRequestService->updateProductionRequest($entityManager, $productionRequest, $currentUser, $inputBag, $request->files, true);

        $entityManager->flush();

        if ($oldStatus->getId() !== $productionRequest->getStatus()->getId()) {
            $productionRequestService->sendUpdateStatusEmail($productionRequest);
        }

        if($data['errors']) {
            return $this->json([
                "success" => true,
                "msg" => $data['errors'],
            ]);
        }

        if ($productionRequestService->checkNeedModalConfirmationForGenerateDispatch($productionRequest, $userService)) {
            return $this->json([
                "success" => true,
                "needModalConfirmationForGenerateDispatch" => true,
                "msg" => "La demande de production a été modifiée avec succès.",
            ]);
        }

        return $this->json([
            "success" => true,
            "msg" => "La demande de production a été modifiée avec succès.",
        ]);
    }

    #[Route("/formulaire-de-duplication/{productionRequest}", name: "form_duplicate", options: ["expose" => true], methods: self::GET, condition: self::IS_XML_HTTP_REQUEST)]
    #[HasPermission([Menu::PRODUCTION, Action::DUPLICATE_PRODUCTION_REQUEST])]
    public function duplicate(ProductionRequest $productionRequest,
                              EntityManagerInterface $entityManager): JsonResponse {
        $fixedFieldByTypeRepository = $entityManager->getRepository(FixedFieldByType::class);
        $productionRequest = clone $productionRequest;

        $productionRequest->clearAttachments();
        $productionType = $productionRequest->getType();

        $fieldsParam = Stream::from($fixedFieldByTypeRepository->findBy([
            "entityCode" => FixedFieldStandard::ENTITY_CODE_PRODUCTION
        ]))
            ->keymap(static fn(FixedFieldByType $field) => [$field->getFieldCode(), [
                FixedFieldByType::ATTRIBUTE_DISPLAYED_EDIT => $field->isDisplayedEdit($productionType),
                FixedFieldByType::ATTRIBUTE_REQUIRED_EDIT => $field->isRequiredEdit($productionType),
            ]])
            ->toArray();

        return $this->json([
            "success" => true,
            "html" => $this->renderView("production_request/modal/form.html.twig", [
                "isDuplication" => true,
                "productionRequest" => $productionRequest,
                "fieldsParam" => $fieldsParam,
                "emergencies" => $fixedFieldByTypeRepository->getElements(FixedFieldStandard::ENTITY_CODE_PRODUCTION, FixedFieldStandard::FIELD_CODE_EMERGENCY),
            ]),
        ]);
    }
}
