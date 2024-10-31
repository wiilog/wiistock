<?php

namespace App\Service\ProductionRequest;

use App\Controller\FieldModesController;
use App\Controller\Settings\StatusController;
use App\Entity\Action;
use App\Entity\CategorieCL;
use App\Entity\CategorieStatut;
use App\Entity\CategoryType;
use App\Entity\Emplacement;
use App\Entity\Fields\FixedFieldEnum;
use App\Entity\Fields\FixedFieldStandard;
use App\Entity\FiltreSup;
use App\Entity\FreeField\FreeField;
use App\Entity\Menu;
use App\Entity\Nature;
use App\Entity\Pack;
use App\Entity\ProductionRequest;
use App\Entity\Setting;
use App\Entity\Statut;
use App\Entity\Tracking\TrackingMovement;
use App\Entity\Type;
use App\Entity\Utilisateur;
use App\Exceptions\FormException;
use App\Exceptions\ImportException;
use App\Service\AttachmentService;
use App\Service\CSVExportService;
use App\Service\DateTimeService;
use App\Service\FieldModesService;
use App\Service\FormatService;
use App\Service\FreeFieldService;
use App\Service\LanguageService;
use App\Service\MailerService;
use App\Service\OperationHistoryService;
use App\Service\PackService;
use App\Service\PlanningService;
use App\Service\SettingsService;
use App\Service\StatusHistoryService;
use App\Service\TrackingMovementService;
use App\Service\TranslationService;
use App\Service\UniqueNumberService;
use App\Service\UserService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\FileBag;
use Symfony\Component\HttpFoundation\InputBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\RouterInterface;
use Twig\Environment as Twig_Environment;
use WiiCommon\Helper\Stream;

class ProductionRequestService
{
    private const MAX_REQUESTS_ON_PLANNING = 2500;

    private ?array $freeFieldsConfig = null;

    public function __construct(
        private TranslationService      $translation,
        private StatusHistoryService    $statusHistoryService,
        private UserService             $userService,
        private MailerService           $mailerService,
        private CSVExportService        $CSVExportService,
        private OperationHistoryService $operationHistoryService,
        private UniqueNumberService     $uniqueNumberService,
        private AttachmentService       $attachmentService,
        private EntityManagerInterface  $entityManager,
        private FreeFieldService        $freeFieldService,
        private Twig_Environment        $templating,
        private RouterInterface         $router,
        private FormatService           $formatService,
        private Security                $security,
        private FieldModesService       $fieldModesService,
        private PlanningService         $planningService,
        private SettingsService         $settingsService,
        private LanguageService         $languageService,
        private DateTimeService         $dateTimeService,
        private TrackingMovementService $trackingMovementService,
        private PackService             $packService,
    )
    {
    }

    public function getVisibleColumnsConfig(EntityManagerInterface $entityManager, ?Utilisateur $currentUser, string $page, bool $forExport = false): array {
        $champLibreRepository = $entityManager->getRepository(FreeField::class);

        $freeFields = $champLibreRepository->findByCategoryTypeAndCategoryCL(CategoryType::PRODUCTION, CategorieCL::PRODUCTION_REQUEST);
        $fieldsModes = $currentUser ? $currentUser->getFieldModes($page) ?? Utilisateur::DEFAULT_FIELDS_MODES[$page] : [];

        $columns = [];

        if (!$forExport) {
            $columns[] = ['name' => 'actions', 'alwaysVisible' => true, 'orderable' => false, 'class' => 'noVis'];
        }

        if ($page === FieldModesController::PAGE_PRODUCTION_REQUEST_PLANNING) {
            $columns[] = [
                'title' => FixedFieldEnum::attachments->value,
                'name' => FixedFieldEnum::attachments->name,
            ];
        }

        $columns = array_merge($columns, [
            ['title' => FixedFieldEnum::number->value, 'name' => FixedFieldEnum::number->name],
            ['title' => FixedFieldEnum::createdAt->value, 'name' => FixedFieldEnum::createdAt->name],
            ['title' => FixedFieldEnum::createdBy->value, 'name' => FixedFieldEnum::createdBy->name],
            ['title' => FixedFieldEnum::treatedBy->value, 'name' => FixedFieldEnum::treatedBy->name],
            ['title' => FixedFieldEnum::type->value, 'name' => FixedFieldEnum::type->name],
            ['title' => FixedFieldEnum::status->value, 'name' => FixedFieldEnum::status->name],
            ['title' => FixedFieldEnum::expectedAt->value, 'name' => FixedFieldEnum::expectedAt->name],
            ['title' => FixedFieldEnum::dropLocation->value, 'name' => FixedFieldEnum::dropLocation->name],
            ['title' => FixedFieldEnum::destinationLocation->value, 'name' => FixedFieldEnum::destinationLocation->name],
            ['title' => FixedFieldEnum::lineCount->value, 'name' => FixedFieldEnum::lineCount->name],
            ['title' => FixedFieldEnum::manufacturingOrderNumber->value, 'name' => FixedFieldEnum::manufacturingOrderNumber->name],
            ['title' => FixedFieldEnum::productArticleCode->value, 'name' => FixedFieldEnum::productArticleCode->name],
            ['title' => FixedFieldEnum::quantity->value, 'name' => FixedFieldEnum::quantity->name],
            ['title' => FixedFieldEnum::emergency->value, 'name' => FixedFieldEnum::emergency->name],
            ['title' => FixedFieldEnum::projectNumber->value, 'name' => FixedFieldEnum::projectNumber->name],
            ['title' => FixedFieldEnum::comment->value, 'name' => FixedFieldEnum::comment->name],
        ]);

        return $this->fieldModesService->getArrayConfig($columns, $freeFields, $fieldsModes, $forExport);
    }

    public function getDataForDatatable(EntityManagerInterface $entityManager, Request $request) : array{
        $productionRepository = $entityManager->getRepository(ProductionRequest::class);

        $fromDashboard = $request->query->getBoolean('fromDashboard');

        if (!$fromDashboard) {
            $filtreSupRepository = $entityManager->getRepository(FiltreSup::class);
            $filters = $filtreSupRepository->getFieldAndValueByPageAndUser(FiltreSup::PAGE_PRODUCTION, $this->userService->getUser());
        } else {
            $preFilledStatuses = $request->query->has('filterStatus')
                ? implode(",", $request->query->all('filterStatus'))
                : [];
            $preFilledTypes = $request->query->has('preFilledTypes')
                ? implode(",", $request->query->all('preFilledTypes'))
                : [];

            $preFilledFilters = [
                [
                    'field' => 'statuses-filter',
                    'value' => $preFilledStatuses,
                ],
                [
                    'field' => FiltreSup::FIELD_MULTIPLE_TYPES,
                    'value' => $preFilledTypes,
                ],
            ];

            $filters = $preFilledFilters;
        }

        $queryResult = $productionRepository->findByParamsAndFilters(
            $request->request,
            $filters,
            $this->fieldModesService,
            [
                'user' => $this->security->getUser(),
            ]
        );

        $productionRequests = $queryResult['data'];

        $rows = [];
        foreach ($productionRequests as $shipping) {
            $rows[] = $this->dataRowProduction($entityManager, $shipping);
        }

        return [
            'data' => $rows,
            'recordsFiltered' => $queryResult['count'],
            'recordsTotal' => $queryResult['total'],
        ];
    }

    public function dataRowProduction(EntityManagerInterface $entityManager, ProductionRequest $productionRequest): array
    {
        $typeColor = $productionRequest->getType()->getColor();

        if (!isset($this->freeFieldsConfig)) {
            $this->freeFieldsConfig = $this->freeFieldService->getListFreeFieldConfig($entityManager, CategorieCL::PRODUCTION_REQUEST, CategoryType::PRODUCTION);
        }

        $productionRequestId = $productionRequest->getId();
        $showRouteName = 'production_request_show';
        $urlShow = $this->router->generate($showRouteName, [
            "id" => $productionRequestId,
        ]);

        $urlEdit = $this->router->generate($showRouteName, [
            "id" => $productionRequestId,
            "open-modal" => "edit",
        ]);

        $row = [
            'actions' => $this->templating->render('utils/action-buttons/dropdown.html.twig', [
                'actions' => [
                    [
                        'title' => 'Détails',
                        'icon' => 'fa fa-eye',
                        'actionOnClick' => true,
                        'href' => $urlShow,
                    ],
                    [
                        'hasRight' => $this->userService->hasRightFunction(Menu::PRODUCTION, Action::DUPLICATE_PRODUCTION_REQUEST),
                        'title' => 'Dupliquer',
                        'icon' => 'fa fa-copy',
                        'class' => 'duplicate-production-request',
                        'attributes' => [
                            'data-target' => '#modalEditProductionRequest',
                            'data-toggle' => 'modal',
                            'data-id' => $productionRequestId,
                        ],
                    ],
                    [
                        'hasRight' => $this->hasRightToEdit($productionRequest),
                        'title' => 'Modifier',
                        'icon' => 'fa fa-pen',
                        'href' => $urlEdit,
                    ],
                    [
                        'hasRight' => $this->hasRightToDelete($productionRequest),
                        'title' => 'Supprimer',
                        'icon' => 'fa fa-trash',
                        'class' => 'delete-production-request',
                        'attributes' => [
                            'data-id' => $productionRequestId,
                        ],
                    ],
                ],
            ]),
            FixedFieldEnum::number->name => $productionRequest->getNumber() ?? '',
            FixedFieldEnum::createdAt->name => $this->formatService->datetime($productionRequest->getCreatedAt()),
            FixedFieldEnum::createdBy->name => $this->formatService->user($productionRequest->getCreatedBy()),
            FixedFieldEnum::treatedBy->name => $this->formatService->user($productionRequest->getTreatedBy()),
            FixedFieldEnum::type->name => "
                <div class='d-flex align-items-center'>
                    <span class='dt-type-color mr-2' style='background-color: $typeColor;'></span>
                    {$this->formatService->type($productionRequest->getType())}
                </div>
            ",
            FixedFieldEnum::status->name => $this->formatService->status($productionRequest->getStatus()),
            FixedFieldEnum::expectedAt->name => $this->formatService->datetime($productionRequest->getExpectedAt()),
            FixedFieldEnum::dropLocation->name => $this->formatService->location($productionRequest->getDropLocation()),
            FixedFieldEnum::destinationLocation->name => $this->formatService->location($productionRequest->getDestinationLocation()),
            FixedFieldEnum::lineCount->name => $productionRequest->getLineCount(),
            FixedFieldEnum::manufacturingOrderNumber->name => $productionRequest->getManufacturingOrderNumber(),
            FixedFieldEnum::productArticleCode->name => $productionRequest->getProductArticleCode(),
            FixedFieldEnum::quantity->name => $productionRequest->getQuantity(),
            FixedFieldEnum::emergency->name => $productionRequest->getEmergency() ?: 'Non',
            FixedFieldEnum::projectNumber->name => $productionRequest->getProjectNumber(),
            FixedFieldEnum::comment->name => $productionRequest->getComment(),
        ];

        foreach ($this->freeFieldsConfig as $freeFieldId => $freeField) {
            $freeFieldName = $this->fieldModesService->getFreeFieldName($freeFieldId);
            $freeFieldValue = $productionRequest->getFreeFieldValue($freeFieldId);
            $row[$freeFieldName] = $this->formatService->freeField($freeFieldValue, $freeField);
        }

        return $row;
    }

    public function updateProductionRequest(EntityManagerInterface $entityManager,
                                            ProductionRequest      $productionRequest,
                                            Utilisateur            $currentUser,
                                            InputBag               $data,
                                            FileBag                $fileBag,
                                            bool $fromUpdateStatus = false): array
    {
        $typeRepository = $entityManager->getRepository(Type::class);
        $statusRepository = $entityManager->getRepository(Statut::class);
        $locationRepository = $entityManager->getRepository(Emplacement::class);
        $oldValues = $productionRequest->serialize();
        $now = new DateTime();

        if(!$productionRequest->getId()){
            $user = $this->userService->getUser();
            $number = $this->uniqueNumberService->create(
                $entityManager,
                ProductionRequest::NUMBER_PREFIX,
                ProductionRequest::class,
                UniqueNumberService::DATE_COUNTER_FORMAT_PRODUCTION_REQUEST,
                $now,
            );

            $productionRequest
                ->setNumber($number)
                ->setCreatedAt($now)
                ->setCreatedBy($user);

            if ($data->has(FixedFieldEnum::type->name)) {
                $type = $typeRepository->find($data->get(FixedFieldEnum::type->name));

                if(!$type->isActive()){
                    throw new FormException("Vous avez sélectionné un type inactif.");
                }

                $productionRequest->setType($type);
            }
        }

        // array_key_exists() needed if creation fieldParams config != edit fieldParams config
        if ($data->has(FixedFieldEnum::status->name)) {
            $status = $statusRepository->find($data->get(FixedFieldEnum::status->name));
            $productionRequest->setStatus($status);

            if ($status->isTreated()) {
                $productionRequest
                    ->setTreatedAt($now);
            }
        }

        $alreadySavedFiles = $data->has('files')
            ? $data->all('files')
            : [];

        $this->attachmentService->removeAttachments($entityManager, $productionRequest, $alreadySavedFiles);
        $addedAttachments = $this->attachmentService->manageAttachments($entityManager, $productionRequest, $fileBag);

        if ($productionRequest->getAttachments()->isEmpty() && $productionRequest->getStatus()->isRequiredAttachment()) {
            throw new FormException("Vous devez ajouter une pièce jointe pour passer à ce statut");
        }

        if ($data->has(FixedFieldEnum::dropLocation->name)) {
            $dropLocationName = $data->get(FixedFieldEnum::dropLocation->name);
            $dropLocation = $dropLocationName ? $locationRepository->find($dropLocationName) : null;
        } else {
            $dropLocation = $productionRequest->getDropLocation() ?: $productionRequest->getType()?->getDropLocation();
        }
        $productionRequest->setDropLocation($dropLocation);

        if ($data->has(FixedFieldEnum::destinationLocation->name)) {
            $destinationLocationName = $data->get(FixedFieldEnum::destinationLocation->name);
            $destinationLocation = $destinationLocationName ? $locationRepository->find($destinationLocationName) : null;
            $productionRequest->setDestinationLocation($destinationLocation);
        }
        if ($data->has(FixedFieldEnum::manufacturingOrderNumber->name)) {
            $productionRequest->setManufacturingOrderNumber($data->get(FixedFieldEnum::manufacturingOrderNumber->name));
        }

        if ($data->has(FixedFieldEnum::emergency->name)) {
            $productionRequest->setEmergency($data->get(FixedFieldEnum::emergency->name));
        }

        if ($data->has(FixedFieldEnum::expectedAt->name)) {
            $productionRequest->setExpectedAt($this->formatService->parseDatetime($data->get(FixedFieldEnum::expectedAt->name)));
        }

        if ($data->has(FixedFieldEnum::projectNumber->name)) {
            $productionRequest->setProjectNumber($data->get(FixedFieldEnum::projectNumber->name));
        }

        if ($data->has(FixedFieldEnum::productArticleCode->name)) {
            $productionRequest->setProductArticleCode($data->get(FixedFieldEnum::productArticleCode->name));
        }

        if ($data->has(FixedFieldEnum::quantity->name)) {
            $productionRequest->setQuantity($data->get(FixedFieldEnum::quantity->name));
        }

        if ($data->has(FixedFieldEnum::lineCount->name)) {
            $productionRequest->setLineCount($data->get(FixedFieldEnum::lineCount->name));
        }

        if ($data->has(FixedFieldEnum::comment->name)) {
            $productionRequest->setComment($data->get(FixedFieldEnum::comment->name));
        }

        if(!$fromUpdateStatus){
            $this->freeFieldService->manageFreeFields($productionRequest, $data->all(), $entityManager);
        }

        $this->persistHistoryRecords(
            $entityManager,
            $productionRequest,
            $currentUser,
            new DateTime(),
            $oldValues,
            $addedAttachments
        );

        $errors = [];
        $status = $statusRepository->find($data->get(FixedFieldEnum::status->name));

        if ($status->isCreateDropMovementOnDropLocation()) {
            $nature = $productionRequest->getStatus()->getType()?->getCreatedIdentifierNature();

            if (!$nature) {
                throw new FormException('Vous devez paramétrer "Nature de l\'identifiant créé" pour créer le mouvement de dépose sur l\'emplacement de dépose.');
            }

            $location = $productionRequest->getDropLocation();
            if (!$location) {
                throw new FormException('Vous devez remplir le champ "Emplacement de dépose" pour créer le mouvement de dépose sur l\'emplacement de dépose.');
            }

            $natureIsAllowedOnDropLocation = $productionRequest->getDropLocation()?->isAllowedNature($nature);

            if (!$natureIsAllowedOnDropLocation) {
                $errors[] = 'Le type de nature n\'est pas autorisé sur cet emplacement';
            }

            $type = $productionRequest->getStatus()->getType();
            $identifier = $type->getCreateDropMovementById();
            $packOrCode = $identifier === Type::CREATE_DROP_MOVEMENT_BY_ID_MANUFACTURING_ORDER_VALUE
                ? $productionRequest->getManufacturingOrderNumber()
                : $productionRequest->getNumber();

            $trackingMovement = $this->trackingMovementService->createTrackingMovement(
                $packOrCode,
                $location,
                $currentUser,
                $now,
                false,
                true,
                TrackingMovement::TYPE_DEPOSE,
                [
                    'quantity' => $productionRequest->getQuantity() ?? null,
                    'from' => $productionRequest,
                    'natureId' => $nature?->getId(),
                ]
            );

            $this->packService->persistLogisticUnitHistoryRecord($entityManager, $trackingMovement->getPack(), [
                "message" => $this->formatService->list([
                    "Associé à" => "{$productionRequest->getNumber()}",
                ]),
                "historyDate" => $now,
                "user" => $currentUser,
                "type" => 'Production',
                "location" => $location,
            ]);

            $customHistoryMessage = $this->buildCustomProductionHistoryMessageForDispatch($trackingMovement->getPack(), $nature, $location);
            $this->operationHistoryService->persistProductionHistory(
                $entityManager,
                $productionRequest,
                OperationHistoryService::TYPE_ADD_DISPATCH,
                [
                    "user" => $currentUser,
                    "date" => $now,
                    "message" => $customHistoryMessage
                ]
            );

            $productionRequest->setLastTracking($trackingMovement);
        }

        return [
            'productionRequest' => $productionRequest,
            'errors' => $errors
        ];
    }

    public function persistHistoryRecords(EntityManagerInterface $entityManager,
                                           ProductionRequest      $productionRequest,
                                           Utilisateur            $currentUser,
                                           DateTime               $date,
                                           array                  $oldValues = [],
                                           array                  $addedAttachments = []): void {
        $oldStatus = $oldValues["status"] ?? null;
        $newStatus = $productionRequest->getStatus();
        if ($newStatus
            && $oldStatus?->getId() !== $newStatus->getId()){
            $statusHistory = $this->statusHistoryService->updateStatus(
                $entityManager,
                $productionRequest,
                $newStatus,
                [
                    "forceCreation" => true,
                    "setStatus" => false,
                    "initiatedBy" => $currentUser,
                    "date" => $date,
                ]
            );

            $entityManager->persist($statusHistory);
        }

        $oldComment = $oldValues['comment'] ?? null;
        $newComment = $productionRequest->getComment();

        if (strip_tags($newComment)
            && $oldComment !== $newComment) {
            $this->operationHistoryService->persistProductionHistory(
                $entityManager,
                $productionRequest,
                OperationHistoryService::TYPE_ADD_COMMENT,
                [
                    "user" => $currentUser,
                    "comment" => $newComment,
                    "statusHistory" => $statusHistory ?? null,
                    "date" => $date,
                ]
            );
        }

        if (!empty($addedAttachments)) {
            $this->operationHistoryService->persistProductionHistory(
                $entityManager,
                $productionRequest,
                OperationHistoryService::TYPE_ADD_ATTACHMENT,
                [
                    "user" => $currentUser,
                    "attachments" => $addedAttachments,
                    "statusHistory" => $statusHistory ?? null,
                    "date" => $date,
                ]
            );
        }

        $customHistoryMessage = $this->buildCustomProductionHistoryMessage($productionRequest, $oldValues);
        $customHistoryMessageStripped = strip_tags($customHistoryMessage);
        if (!$productionRequest->getId()) { // creation
            $this->operationHistoryService->persistProductionHistory(
                $entityManager,
                $productionRequest,
                OperationHistoryService::TYPE_REQUEST_CREATION,
                [
                    "user" => $productionRequest->getCreatedBy(),
                    "date" => $productionRequest->getCreatedAt(),
                    "statusHistory" => $statusHistory ?? null,
                    "message" => $customHistoryMessageStripped ? $customHistoryMessage : null
                ]
            );
        }
        else if($customHistoryMessageStripped){ // edit
            $this->operationHistoryService->persistProductionHistory(
                $entityManager,
                $productionRequest,
                OperationHistoryService::TYPE_REQUEST_EDITED_DETAILS,
                [
                    "user" => $currentUser,
                    "message" => $customHistoryMessage,
                    "date" => $date,
                ]
            );
        }
    }

    public function createHeaderDetailsConfig(ProductionRequest $productionRequest): array {
        $config = [
            [
                'label' => FixedFieldEnum::manufacturingOrderNumber->value,
                'value' => $productionRequest->getManufacturingOrderNumber(),
                'show' => ['fieldName' => FixedFieldEnum::manufacturingOrderNumber->name],
            ],
            [
                'label' => FixedFieldEnum::createdAt->value,
                'value' =>  $this->formatService->datetime($productionRequest->getCreatedAt()),
            ],
            [
                'label' => FixedFieldEnum::expectedAt->value,
                'value' => $this->formatService->datetime($productionRequest->getExpectedAt()),
                'show' => ['fieldName' => FixedFieldEnum::expectedAt->name],
            ],
            [
                'label' => FixedFieldEnum::projectNumber->value,
                'value' => $productionRequest->getProjectNumber(),
                'show' => ['fieldName' => FixedFieldEnum::projectNumber->name],
            ],
            [
                'label' => FixedFieldEnum::productArticleCode->value,
                'value' => $productionRequest->getProductArticleCode(),
                'show' => ['fieldName' => FixedFieldEnum::productArticleCode->name],
            ],
            [
                'label' => FixedFieldEnum::quantity->value,
                'value' => $productionRequest->getQuantity(),
                'show' => ['fieldName' => FixedFieldEnum::quantity->name],
            ],
            [
                'label' => FixedFieldEnum::dropLocation->value,
                'value' => $this->formatService->location($productionRequest->getDropLocation()),
                'show' => ['fieldName' => FixedFieldEnum::dropLocation->name],

            ],
            [
                'label' => FixedFieldEnum::destinationLocation->value,
                'value' => $this->formatService->location($productionRequest->getDestinationLocation()),
                'show' => ['fieldName' => FixedFieldEnum::destinationLocation->name],

            ],
            [
                'label' => FixedFieldEnum::lineCount->value,
                'value' => $productionRequest->getLineCount(),
                'show' => ['fieldName' => FixedFieldEnum::lineCount->name],
            ],
        ];

        return $config;
    }

    public function buildCustomProductionHistoryMessageForDispatch(Pack $pack, Nature $nature, Emplacement $location): string {
        return sprintf(
            "<br/><strong>Unité logistique</strong> : %s<br/>" .
            "<strong>Nature</strong> : %s<br/>" .
            "<strong>Emplacement</strong> : %s<br/>",
            $pack->getCode(),
            $nature->getCode(),
            $location->getLabel(),
        );
    }


    public function buildCustomProductionHistoryMessage(ProductionRequest $productionRequest,
                                                        array             $oldValues): string {
        $oldDropLocation = $oldValues[FixedFieldEnum::dropLocation->name] ?? null;
        $oldManufacturingOrderNumber = $oldValues[FixedFieldEnum::manufacturingOrderNumber->name] ?? null;
        $oldEmergency = $oldValues[FixedFieldEnum::emergency->name] ?? null;
        $oldExpectedAt = $oldValues[FixedFieldEnum::expectedAt->name] ?? null;
        $oldProjectNumber = $oldValues[FixedFieldEnum::projectNumber->name] ?? null;
        $oldProductArticleCode = $oldValues[FixedFieldEnum::productArticleCode->name] ?? null;
        $oldQuantity = $oldValues[FixedFieldEnum::quantity->name] ?? null;
        $oldLineCount = $oldValues[FixedFieldEnum::lineCount->name] ?? null;

        $message = "<br>";
        if ($productionRequest->getDropLocation()
            && $oldDropLocation?->getId() !== $productionRequest->getDropLocation()->getId()) {
            $message .= "<strong>".FixedFieldEnum::dropLocation->value."</strong> : {$productionRequest->getDropLocation()->getLabel()}<br>";
        }

        if ($productionRequest->getManufacturingOrderNumber()
            && $oldManufacturingOrderNumber !== $productionRequest->getManufacturingOrderNumber()) {
            $message .= "<strong>".FixedFieldEnum::manufacturingOrderNumber->value."</strong> : {$productionRequest->getManufacturingOrderNumber()} <br>";
        }

        if ($productionRequest->getEmergency()
            && $oldEmergency !== $productionRequest->getEmergency()) {
            $message .= "<strong>".FixedFieldEnum::emergency->value."</strong> : {$productionRequest->getEmergency()}<br>";
        }

        if ($productionRequest->getExpectedAt()
            && $oldExpectedAt?->format("Y-m-d H:i") !== $productionRequest->getExpectedAt()->format("Y-m-d H:i")) {
            $newExpectedAt = $this->formatService->datetime($productionRequest->getExpectedAt(), "", true);
            $message .= "<strong>".FixedFieldEnum::expectedAt->value."</strong> : {$newExpectedAt}<br>";
        }

        if ($productionRequest->getProjectNumber()
            && $oldProjectNumber !== $productionRequest->getProjectNumber()) {
            $message .= "<strong>".FixedFieldEnum::projectNumber->value."</strong> : {$productionRequest->getProjectNumber()}<br>";
        }

        if ($productionRequest->getProductArticleCode()
            && $oldProductArticleCode !== $productionRequest->getProductArticleCode()) {
            $message .= "<strong>".FixedFieldEnum::productArticleCode->value."</strong> : {$productionRequest->getProductArticleCode()}<br>";
        }

        if ($productionRequest->getQuantity()
            && intval($oldQuantity) !== intval($productionRequest->getQuantity())) {
            $message .= "<strong>".FixedFieldEnum::quantity->value."</strong> : {$productionRequest->getQuantity()}<br>";
        }

        if ($productionRequest->getLineCount()
            && intval($oldLineCount) !== intval($productionRequest->getLineCount())) {
            $message .= "<strong>".FixedFieldEnum::lineCount->value."</strong> : {$productionRequest->getLineCount()}<br>";
        }

        Stream::from($productionRequest->getFreeFields())
            ->each(function($freeFieldValue, $freeFieldId) use ($oldValues, $productionRequest, &$message) {
                $freeFieldRepository = $this->entityManager->getRepository(FreeField::class);
                $freeField = $freeFieldRepository->find($freeFieldId);
                $freeFieldAdded = (!isset($oldValues[$freeFieldId]) && (!empty($freeFieldValue) || $freeField->getTypage() === FreeField::TYPE_BOOL));
                $freeFieldEdited = (isset($oldValues[$freeFieldId]) && $oldValues[$freeFieldId] !== $freeFieldValue);
                if ($freeFieldAdded || $freeFieldEdited){
                    $message .= "<strong>{$freeField->getLabel()}</strong> : {$this->formatService->freeField($freeFieldValue, $freeField)} <br>";
                }
            });

        return $message;
    }

    public function productionRequestPutLine($output, array $productionRequest, array $freeFieldsConfig, array $freeFieldsById): void {
        $freeFieldValues = $freeFieldsById[$productionRequest['id']];
        $comment = $productionRequest[FixedFieldEnum::comment->name];

        $row = [
            $productionRequest[FixedFieldEnum::number->name],
            $productionRequest[FixedFieldEnum::createdAt->name],
            $productionRequest[FixedFieldEnum::createdBy->name],
            $productionRequest[FixedFieldEnum::treatedBy->name],
            $productionRequest[FixedFieldEnum::type->name],
            $productionRequest[FixedFieldEnum::status->name],
            $productionRequest[FixedFieldEnum::expectedAt->name],
            $productionRequest[FixedFieldEnum::dropLocation->name],
            $productionRequest[FixedFieldEnum::destinationLocation->name],
            $productionRequest[FixedFieldEnum::lineCount->name],
            $productionRequest[FixedFieldEnum::manufacturingOrderNumber->name],
            $productionRequest[FixedFieldEnum::productArticleCode->name],
            $productionRequest[FixedFieldEnum::quantity->name],
            $productionRequest[FixedFieldEnum::emergency->name],
            $productionRequest[FixedFieldEnum::projectNumber->name],
            $comment ? strip_tags($comment) : null,
            ...(Stream::from($freeFieldsConfig['freeFields'])
                ->map(function(FreeField $freeField, $freeFieldId) use ($freeFieldValues) {
                    $value = $freeFieldValues[$freeFieldId] ?? null;
                    return $value
                        ? $this->formatService->freeField($value, $freeField)
                        : $value;
                })
                ->values()),
        ];

        $this->CSVExportService->putLine($output, $row);
    }

    /**
     * Checking right in front side in production_request/planning/card.html.twig
     */
    public function checkRoleForEdition(ProductionRequest $productionRequest): void {
        if (!self::hasRightToUpdateStatus($productionRequest)) {
            throw new FormException("Vous n'avez pas les droits pour modifier cette demande de production");
        }
    }

    public function hasRightToUpdateStatus(ProductionRequest $productionRequest):bool {
        $status = $productionRequest->getStatus();

        return (
            $status
            && (
                ($status->isInProgress() && $this->userService->hasRightFunction(Menu::PRODUCTION, Action::EDIT_IN_PROGRESS_PRODUCTION_REQUEST))
                || ($status->isNotTreated() && $this->userService->hasRightFunction(Menu::PRODUCTION, Action::EDIT_TO_TREAT_PRODUCTION_REQUEST))
                || ($status->isPartial() && $this->userService->hasRightFunction(Menu::PRODUCTION, Action::EDIT_PARTIAL_PRODUCTION_REQUEST))
                || ($status->isTreated() && $this->userService->hasRightFunction(Menu::PRODUCTION, Action::EDIT_TREATED_PRODUCTION_REQUEST))
            )
        );
    }


    public function parseRequestForCard(ProductionRequest $productionRequest): array {
        $requestStatus = $productionRequest->getStatus()?->getCode();
        $requestState = $productionRequest->getStatus()?->getState();
        $requestType = $this->formatService->type($productionRequest->getType());

        $productionRequestExpectedAT = $productionRequest->getExpectedAt() ? $this->formatService->datetime($productionRequest->getExpectedAt()) : '';
        $estimatedFinishTimeLabel = $productionRequest->getExpectedAt() ? 'Date attendue' : '';

        $href = $this->router->generate('production_request_show', ['id' => $productionRequest->getId()]);

        $bodyTitle = "{$productionRequest->getManufacturingOrderNumber()} - $requestType";

        $statusesToProgress = [
            Statut::TREATED => 100,
            Statut::NOT_TREATED => 30,
            Statut::IN_PROGRESS => 60,
        ];
        return [
            'href' => $href ?? null,
            'errorMessage' => 'Vous n\'avez pas les droits d\'accéder à la page de la demande de production',
            'estimatedFinishTime' => $productionRequestExpectedAT,
            'estimatedFinishTimeLabel' => $estimatedFinishTimeLabel,
            'requestStatus' => $requestStatus,
            'requestBodyTitle' => $bodyTitle,
            'requestLocation' => $this->formatService->location($productionRequest->getDropLocation(), 'Non défini'),
            'requestNumber' => $productionRequest->getNumber(),
            'requestDate' => $this->formatService->datetime($productionRequest->getCreatedAt()),
            'requestUser' => $this->formatService->user($productionRequest->getTreatedBy(), 'Non défini'),
            'cardColor' => 'white',
            'bodyColor' => 'light-grey',
            'topRightIcon' => 'livreur.svg',
            'emergencyText' => '',
            'progress' => $statusesToProgress[$requestState] ?? 0,
            'progressBarColor' => '#2ec2ab',
            'progressBarBGColor' => 'light-grey',
        ];
    }

    public function sendUpdateStatusEmail(ProductionRequest $productionRequest): void {
        $settingRepository = $this->entityManager->getRepository(Setting::class);
        $userRepository = $this->entityManager->getRepository(Utilisateur::class);

        $typeLabel = $this->formatService->type($productionRequest->getType());
        $status = $productionRequest->getStatus();
        $isTreatedStatus = $status->isTreated();
        $isNew = $productionRequest->getStatusHistory()->count() === 1;

        $subject = $this->translation->translate('Général', null, 'Header', 'Wiilog', false) . MailerService::OBJECT_SERPARATOR;
        $subject .= $isNew && !$isTreatedStatus
            ? "Notification de création d'une demande de production"
            : (!$isTreatedStatus
                ? "Notification d'évolution de votre demande de production"
                : "Notification de traitement de votre demande de production"
            );
        $state = !$isNew ? "Changement de statut" : "Création";
        $title = "$state d'une demande de production de type $typeLabel vous concernant :";

        $to = [];
        $sendingEmailEveryStatusChangeIfEmergency = $settingRepository->getOneParamByLabel(Setting::SENDING_EMAIL_EVERY_STATUS_CHANGE_IF_EMERGENCY);
        $copyingRequesterNotificationEmailIfEmergency = $settingRepository->getOneParamByLabel(Setting::COPYING_REQUESTER_NOTIFICATION_EMAIL_IF_EMERGENCY);
        $hasEmergency = !!$productionRequest->getEmergency();
        if($hasEmergency) {
            if($sendingEmailEveryStatusChangeIfEmergency) {
                $sendingEmailEveryStatusChangeIfEmergencyUsers = $settingRepository->getOneParamByLabel(Setting::SENDING_EMAIL_EVERY_STATUS_CHANGE_IF_EMERGENCY_USERS);

                if($sendingEmailEveryStatusChangeIfEmergencyUsers) {
                    $users = $userRepository->findBy(["id" => explode(",", $sendingEmailEveryStatusChangeIfEmergencyUsers)]);
                    array_push($to, ...$users);
                }
            }

            if($copyingRequesterNotificationEmailIfEmergency) {
                $to[] = $userRepository->find($copyingRequesterNotificationEmailIfEmergency);
            }
        }

        if($status->getSendNotifToDeclarant()) {
            $to[] = $productionRequest->getCreatedBy();
        }

        $notifiedUsers = $status->getNotifiedUsers();
        if(!$notifiedUsers->isEmpty()) {
            array_push($to, ...$notifiedUsers);
        }

        $this->mailerService->sendMail(
            $subject,
            $this->templating->render("mails/production_request/updated-status.html.twig", [
                "productionRequest" => $productionRequest,
                "title" => $title,
                "isNew" => $isNew,
                "isTreatedStatus" => $isTreatedStatus,
            ]),
            $to,
        );
    }

    public function importProductionRequest(EntityManagerInterface $entityManager,
                                            array                  $data,
                                            Utilisateur            $importUser,
                                            ?bool                  &$isCreation): void {

        $typeRepository = $entityManager->getRepository(Type::class);
        $statusRepository = $entityManager->getRepository(Statut::class);
        $locationRepository = $entityManager->getRepository(Emplacement::class);
        $userRepository = $entityManager->getRepository(Utilisateur::class);

        $now = new DateTime();
        $productionRequest = new ProductionRequest();

        $number = $this->uniqueNumberService->create($entityManager, ProductionRequest::NUMBER_PREFIX, ProductionRequest::class, UniqueNumberService::DATE_COUNTER_FORMAT_PRODUCTION_REQUEST, $now);

        if(!empty($data[FixedFieldEnum::createdBy->name])) {
            $user = $userRepository->findOneBy(['username' => $data[FixedFieldEnum::createdBy->name]]);
            if (empty($user)) {
                throw new ImportException("La colonne " . FixedFieldEnum::createdBy->value . " n'est pas valide.");
            }
        }
        else {
            $user = $importUser;
        }

        $productionRequest
            ->setNumber($number)
            ->setCreatedAt($now)
            ->setCreatedBy($user)
            ->setManufacturingOrderNumber($data[FixedFieldEnum::manufacturingOrderNumber->name]);

        if (isset($data[FixedFieldEnum::type->name])) {
            $type = $typeRepository->findOneByCategoryLabelAndLabel(CategoryType::PRODUCTION, $data[FixedFieldEnum::type->name]);

            if ($type) {
                $productionRequest->setType($type);
            } else if(!$type->isActive()) {
                throw new ImportException("Le type n'est pas actif.");
            } else {
                throw new ImportException("Le type n'existe pas.");
            }
        }

        if (isset($data[FixedFieldEnum::status->name])) {
            $status = $statusRepository->findOneBy([
                "nom" => $data[FixedFieldEnum::status->name],
                "type" => $productionRequest->getType(),
            ]);

            if ($status) {
                $productionRequest->setStatus($status);

                if ($status->isTreated()) {
                    $productionRequest
                        ->setTreatedBy($user)
                        ->setTreatedAt($now);
                }
            }
            else {
                throw new ImportException("Le statut n'existe pas ou n'est pas lié au type.");
            }
        }

        if (isset($data[FixedFieldEnum::expectedAt->name])) {
            $expectedAt = $this->formatService->parseDatetime($data[FixedFieldEnum::expectedAt->name]);

            if ($expectedAt) {
                $productionRequest->setExpectedAt($expectedAt);
            } else {
                throw new ImportException("Le format de la date attendue n'est pas valide.");
            }
        }

        if (isset($data[FixedFieldEnum::emergency->name])) {
            $productionRequest->setEmergency($data[FixedFieldEnum::emergency->name]);
        }

        if (isset($data[FixedFieldEnum::projectNumber->name])) {
            $productionRequest->setProjectNumber($data[FixedFieldEnum::projectNumber->name]);
        }

        if (isset($data[FixedFieldEnum::productArticleCode->name])) {
            $productionRequest->setProductArticleCode($data[FixedFieldEnum::productArticleCode->name]);
        }

        if (isset($data[FixedFieldEnum::dropLocation->name])) {
            $dropLocation = $locationRepository->findOneBy(["label" => $data[FixedFieldEnum::dropLocation->name]]);
            if ($dropLocation) {
                $productionRequest->setDropLocation($dropLocation);
            } else {
                throw new ImportException("L'emplacement de dépose n'existe pas.");
            }
        }

        if (isset($data[FixedFieldEnum::destinationLocation->name])) {
            $destinationLocation = $locationRepository->findOneBy(["label" => $data[FixedFieldEnum::destinationLocation->name]]);
            if ($destinationLocation) {
                $productionRequest->setDestinationLocation($destinationLocation);
            } else {
                throw new ImportException("L'emplacement de destination n'existe pas.");
            }
        }

        if (isset($data[FixedFieldEnum::comment->name])) {
            $productionRequest->setComment($data[FixedFieldEnum::comment->name]);
        }

        if (isset($data[FixedFieldEnum::quantity->name])) {
            $productionRequest->setQuantity(intval($data[FixedFieldEnum::quantity->name]));
        }

        if (isset($data[FixedFieldEnum::lineCount->name])) {
            $productionRequest->setLineCount(intval($data[FixedFieldEnum::lineCount->name]));
        }

        $this->persistHistoryRecords($entityManager, $productionRequest, $user, $now);

        $entityManager->persist($productionRequest);

        $isCreation = true; // increment new entity counter
    }

    public function hasRightToDelete(ProductionRequest $productionRequest): bool {
        $status = $productionRequest->getStatus();
        if ($status->isNotTreated()) {
            return $this->userService->hasRightFunction(Menu::PRODUCTION, Action::DELETE_TO_TREAT_PRODUCTION_REQUEST);
        }
        if ($status->isInProgress()) {
            return $this->userService->hasRightFunction(Menu::PRODUCTION, Action::DELETE_IN_PROGRESS_PRODUCTION_REQUEST);
        }
        if ($status->isTreated()) {
            return $this->userService->hasRightFunction(Menu::PRODUCTION, Action::DELETE_TREATED_PRODUCTION_REQUEST);
        }
        return false;
    }

    public function hasRightToEdit(ProductionRequest $productionRequest): bool {
        $status = $productionRequest->getStatus();
        if ($status->isNotTreated()) {
            return $this->userService->hasRightFunction(Menu::PRODUCTION, Action::EDIT_TO_TREAT_PRODUCTION_REQUEST);
        }
        if ($status->isInProgress()) {
            return $this->userService->hasRightFunction(Menu::PRODUCTION, Action::EDIT_IN_PROGRESS_PRODUCTION_REQUEST);
        }
        if ($status->isPartial()) {
            return $this->userService->hasRightFunction(Menu::PRODUCTION, Action::EDIT_PARTIAL_PRODUCTION_REQUEST);
        }
        if ($status->isTreated()) {
            return $this->userService->hasRightFunction(Menu::PRODUCTION, Action::EDIT_TREATED_PRODUCTION_REQUEST);
        }
        return false;
    }

    public function getDisplayedFieldsConfig(bool  $external,
                                             array $fieldModes): array {

        $fields = [
            [
                "field" => FixedFieldEnum::status,
                "type" => "tags",
                "getDetails" => fn(ProductionRequest $productionRequest) => [
                    "class" => !$external && $this->hasRightToUpdateStatus($productionRequest) && !$productionRequest->getStatus()->isTreated() ? "prevent-default open-modal-update-production-request-status" : "",
                    "color" => $productionRequest->getStatus()->getColor(),
                    "label" => $this->formatService->status($productionRequest->getStatus()),
                ],
            ],
            [
                "field" => FixedFieldEnum::productArticleCode,
                "type" => "rows",
                "getDetails" => static fn(ProductionRequest $productionRequest, FixedFieldEnum $field) => [
                    "label" => $field->value,
                    "value" => $productionRequest->getProductArticleCode(),
                ],
            ],
            [
                "field" => FixedFieldEnum::manufacturingOrderNumber,
                "type" => "rows",
                "getDetails" => static fn(ProductionRequest $productionRequest, FixedFieldEnum $field) => [
                    "label" => $field->value,
                    "value" => $productionRequest->getManufacturingOrderNumber(),
                ],

            ],
            [
                "field" => FixedFieldEnum::dropLocation,
                "type" => "rows",
                "getDetails" => fn(ProductionRequest $productionRequest, FixedFieldEnum $field) => [
                    "label" => $field->value,
                    "value" => $this->formatService->location($productionRequest->getDropLocation()),
                ],
            ],
            [
                "field" => FixedFieldEnum::destinationLocation,
                "type" => "rows",
                "getDetails" => fn(ProductionRequest $productionRequest, FixedFieldEnum $field) => [
                    "label" => $field->value,
                    "value" => $this->formatService->location($productionRequest->getDestinationLocation()),
                ],
            ],
            [
                "field" => FixedFieldEnum::quantity,
                "type" => "rows",
                "getDetails" => static fn(ProductionRequest $productionRequest, FixedFieldEnum $field) => [
                    "label" => $field->value,
                    "value" =>$productionRequest->getQuantity(),
                ],
            ],
            [
                "field" => FixedFieldEnum::emergency,
                "type" => "icons",
                "getDetails" => static function(ProductionRequest $productionRequest, FixedFieldEnum $field) {
                    $emergency = $productionRequest->getEmergency();
                    return $emergency
                        ? [
                            "path" => "svg/urgence.svg",
                            "alt" => "icon $field->value",
                            "title" => "Une urgence est en cours sur cette demande : $emergency",
                        ]
                        : null;
                },
            ],
            [
                "field" => FixedFieldEnum::comment,
                "type" => "icons",
                "getDetails" => static function(ProductionRequest $productionRequest, FixedFieldEnum $field) {
                    $comment = strip_tags($productionRequest->getComment());
                    return $comment
                        ? [
                            "path" => "svg/comment-dots-regular.svg",
                            "alt" => "icon $field->value",
                            "title" => "Un commentaire est présent sur cette demande : $comment",
                        ]
                        : null;
                },
            ],
            [
                "field" => FixedFieldEnum::lineCount,
                "type" => "rows",
                "getDetails" => static fn(ProductionRequest $productionRequest, FixedFieldEnum $field) => [
                    "label" => $field->value,
                    "value" =>$productionRequest->getLineCount(),
                ],
            ],
            [
                "field" => FixedFieldEnum::projectNumber,
                "type" => "rows",
                "getDetails" =>  static fn(ProductionRequest $productionRequest, FixedFieldEnum $field) => [
                    "label" => $field->value,
                    "value" =>$productionRequest->getProjectNumber(),
                ],
            ],
            [
                "field" => FixedFieldEnum::attachments,
                "type" => "icons",
                "getDetails" => static function(ProductionRequest $productionRequest, FixedFieldEnum $field) {
                    $attachmentsCount = $productionRequest->getAttachments()->count();
                    return $attachmentsCount
                        ? [
                            "path" => "svg/paperclip.svg",
                            "alt" => "icon $field->value",
                            "title" => "$attachmentsCount pièce(s) jointe(s) est/sont présente(s) sur cette demande",
                        ]
                        : null;
                },
            ],
            [
                "field" => FixedFieldEnum::createdBy,
                "type" => "rows",
                "getDetails" => fn(ProductionRequest $productionRequest, FixedFieldEnum $field) => [
                    "label" => $field->value,
                    "value" => $this->formatService->user($productionRequest->getCreatedBy()),
                ],
            ],
            [
                "field" => FixedFieldEnum::type,
                "type" => "rows",
                "getDetails" => fn(ProductionRequest $productionRequest, FixedFieldEnum $field) => [
                    "label" => $field->value,
                    "value" => $this->formatService->type($productionRequest->getType()),
                ],
            ],
            [
                "field" => FixedFieldEnum::treatedBy,
                "type" => "rows",
                "getDetails" => fn(ProductionRequest $productionRequest, FixedFieldEnum $field) => [
                        "label" => $field->value,
                        "value" => $this->formatService->user($productionRequest->getTreatedBy()),
                    ],
            ],
            [
                "field" => FixedFieldEnum::expectedAt,
                "type" => "rows",
                "getDetails" => fn(ProductionRequest $productionRequest, FixedFieldEnum $field) => [
                        "label" => $field->value,
                        "value" => $this->formatService->datetime($productionRequest->getExpectedAt()),
                    ],
            ],
            [
                "field" => FixedFieldEnum::createdAt,
                "type" => "rows",
                "getDetails" => fn(ProductionRequest $productionRequest, FixedFieldEnum $field) => [
                    "label" => $field->value,
                    "value" => $this->formatService->datetime($productionRequest->getCreatedAt()),
                ],
            ],
            [
                "field" => FixedFieldEnum::number,
                "type" => "rows",
                "getDetails" => static fn(ProductionRequest $productionRequest, FixedFieldEnum $field) => [
                    "label" => $field->value,
                    "value" => $productionRequest->getNumber(),
                ],
            ],
        ];

        $displayedFieldsConfig = [];
        foreach ($fields as $fieldData) {
            $fieldLocation = $this->planningService->getFieldDisplayConfig($fieldData["field"]->name, $fieldModes);
            if($fieldLocation) {
                $displayedFieldsConfig[$fieldLocation][$fieldData["type"]][] = $fieldData;
            }
        }

        return $displayedFieldsConfig;
    }

    public function createPlanningConfig(EntityManagerInterface $entityManager,
                                         Request                $request,
                                         bool                   $external): array {
        $productionRequestRepository = $entityManager->getRepository(ProductionRequest::class);
        $statusRepository = $entityManager->getRepository(Statut::class);
        $supFilterRepository = $entityManager->getRepository(FiltreSup::class);
        $fixedFieldRepository = $entityManager->getRepository(FixedFieldStandard::class);


        $user = $this->userService->getUser();
        $userLanguage = $user?->getLanguage() ?: $this->languageService->getDefaultLanguage();

        $planningStart = $this->formatService->parseDatetime($request->query->get('startDate'));

        $step = $request->query->getint('step', PlanningService::NB_DAYS_ON_PLANNING);
        $planningEnd = (clone $planningStart)->modify("+$step days");

        $sortingType = $request->query->get('sortingType');


        $fieldModes = $user?->getFieldModes(FieldModesController::PAGE_PRODUCTION_REQUEST_PLANNING) ?? Utilisateur::DEFAULT_PRODUCTION_REQUEST_PLANNING_FIELDS_MODES;
        $displayedFieldsConfig = $this->getDisplayedFieldsConfig($external, $fieldModes);

        $filters = [];
        if(!$external) {
            $filters = Stream::from($supFilterRepository->getFieldAndValueByPageAndUser(FiltreSup::PAGE_PRODUCTION_PLANNING, $user))
                ->filter(static fn(array $filter) => ($filter["value"] != "" &&  in_array($filter["field"], [
                        FiltreSup::FIELD_REQUEST_NUMBER,
                        FiltreSup::FIELD_MULTIPLE_TYPES,
                        FiltreSup::FIELD_OPERATORS,
                        'statuses-filter',
                    ])))
                ->toArray();
        }

        $statuses = Stream::from($statusRepository->findByCategorieName(CategorieStatut::PRODUCTION))
            ->filter(static fn(Statut $status) => $status->isDisplayedOnSchedule())
            ->toArray();

        $productionRequests = $productionRequestRepository->findByStatusCodesAndExpectedAt($filters, $statuses, $planningStart, $planningEnd);
        if (count($productionRequests) > self::MAX_REQUESTS_ON_PLANNING) {
            throw new FormException('Il y a trop de demandes de production pour cette période, veuillez affiner votre recherche.');
        }

        $fixedFieldParamCountLines  = $fixedFieldRepository->findByEntityCode(FixedFieldStandard::ENTITY_CODE_PRODUCTION, [FixedFieldEnum::lineCount->name])[0] ?? null;
        $displayCountLines = $fixedFieldParamCountLines?->isDisplayedEdit() || $fixedFieldParamCountLines?->isDisplayedCreate();

        $cards = [];
        $linesCountByColumns = [];

        foreach ($productionRequests as $productionRequest) {
            $cardContent = $this->planningService->createCardConfig($displayedFieldsConfig, $productionRequest, $fieldModes, $userLanguage);
            $columnId = match ($sortingType) {
                PlanningService::SORTING_TYPE_BY_DATE => $productionRequest->getExpectedAt()->format('Y-m-d'),
                PlanningService::SORTING_TYPE_BY_STATUS_STATE => $productionRequest->getStatus()->getState(),
                default => throw new BadRequestHttpException(),
            };

            $cards[$columnId][] = $this->templating->render('utils/planning/card.html.twig', [
                "color" => $productionRequest->getType()->getColor() ?: Type::DEFAULT_COLOR,
                "cardContent" => $cardContent,
                ...$this->generateAdditionalCardConfig($entityManager, $productionRequest, $external, $sortingType),
            ]);
            if ($displayCountLines) {
                $linesCountByColumns[$columnId] = ($linesCountByColumns[$columnId] ?? 0) + $productionRequest->getLineCount();
            }
        }

        $options = [];
        if ($displayCountLines) {
            $options["columnRightHints"] = Stream::from($linesCountByColumns)
                ->map(function (int $count) {
                    $plurialMark = $count > 1 ? 's' : '';
                    return "$count ligne$plurialMark";
                })
                ->toArray();
        }

        $groupedProductionRequests = [];
        $averageTimeByType = [];

        foreach ($productionRequests as $productionRequest) {
            $columnId = match ($sortingType) {
                PlanningService::SORTING_TYPE_BY_DATE => $productionRequest->getExpectedAt()->format('Y-m-d'),
                PlanningService::SORTING_TYPE_BY_STATUS_STATE => $productionRequest->getStatus()->getState(),
                default => throw new BadRequestHttpException(),
            };

            $groupedProductionRequests[$columnId][] = $productionRequest;
        }

        foreach ($groupedProductionRequests as $columnId => $productionRequestsColumn) {
            $totalMinutes = Stream::from($productionRequestsColumn)
                ->reduce(function ($totalMinutesPerDay, ProductionRequest $productionRequest) {
                    $averageTimeByDay = $productionRequest->getType()->getAverageTime();

                    if ($averageTimeByDay) {
                        return $totalMinutesPerDay + $this->dateTimeService->calculateMinuteFrom($averageTimeByDay);
                    }

                    return $totalMinutesPerDay;
                }, 0);

            // convert minutes to this format: HH h MM
            $averageTimeByType[$columnId] = $totalMinutes > 0
                ? $this->formatService->datetimeToString($this->formatService->minutesToDatetime($totalMinutes))
                : null;
        }

        $options["columnRightInfos"] = $averageTimeByType;

        return $this->planningService->createPlanningConfig(
            $entityManager,
            $planningStart,
            $step,
            $sortingType,
            StatusController::MODE_PRODUCTION,
            $cards,
            $options
        );
    }

    private function generateAdditionalCardConfig(EntityManagerInterface $entityManager,
                                                  ProductionRequest      $productionRequest,
                                                  bool                   $external,
                                                  string                 $sortingType): array {
        $additionalClasses = ["has-tooltip"];

        if (!$external && $sortingType == PlanningService::SORTING_TYPE_BY_DATE && $this->settingsService->getValue($entityManager, Menu::PRODUCTION, Action::EDIT_EXPECTED_DATE_FIELD_PRODUCTION_REQUEST)) {
            $additionalClasses[] = "pointer";
            $additionalClasses[] = "can-drag";
        }

        $expectedAt = $this->formatService->longDate($productionRequest->getExpectedAt());
        $additionalAttributes = [
            ["name" => "data-id", "value" => $productionRequest->getId()],
            ["name" => "title", "value" => "Attendu le $expectedAt"],
        ];

        if (!$external) {
            $additionalAttributes[] = ["name" => "href", "value" => $this->router->generate('production_request_show', ['id' => $productionRequest->getId()])];
            $additionalAttributes[] = ["name" => "data-status", "value" => $productionRequest->getStatus()->getCode()];
        }

        return [
            "additionalClasses" => $additionalClasses,
            "additionalAttributes" => $additionalAttributes,
        ];
    }

}
