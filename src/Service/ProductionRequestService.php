<?php

namespace App\Service;

use App\Entity\Action;
use App\Entity\CategorieCL;
use App\Entity\CategoryType;
use App\Entity\Fields\FixedFieldEnum;
use App\Entity\FiltreSup;
use App\Entity\FreeField;
use App\Entity\Menu;
use App\Entity\ProductionRequest;
use App\Entity\Utilisateur;
use App\Exceptions\FormException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\InputBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Service\Attribute\Required;
use Twig\Environment as Twig_Environment;
use App\Entity\Attachment;
use App\Entity\Emplacement;
use App\Entity\Fields\FixedFieldStandard;
use App\Entity\Statut;
use App\Entity\Type;
use DateTime;
use Symfony\Component\HttpFoundation\FileBag;
use WiiCommon\Helper\Stream;

class ProductionRequestService
{

    #[Required]
    public VisibleColumnService $visibleColumnService;

    #[Required]
    public Security $security;

    #[Required]
    public FormatService $formatService;

    #[Required]
    public RouterInterface $router;

    #[Required]
    public Twig_Environment $templating;

    #[Required]
    public FreeFieldService $freeFieldService;

    #[Required]
    public EntityManagerInterface $entityManager;

    #[Required]
    public AttachmentService $attachmentService;

    #[Required]
    public UniqueNumberService $uniqueNumberService;

    #[Required]
    public FixedFieldService $fixedFieldService;

    #[Required]
    public UserService $userService;

    #[Required]
    public StatusHistoryService $statusHistoryService;

    #[Required]
    public OperationHistoryService $operationHistoryService;

    #[Required]
    public CSVExportService $CSVExportService;

    private ?array $freeFieldsConfig = null;

    public function getVisibleColumnsConfig(EntityManagerInterface $entityManager, Utilisateur $currentUser, bool $forExport = false): array {
        $champLibreRepository = $entityManager->getRepository(FreeField::class);

        $freeFields = $champLibreRepository->findByCategoryTypeAndCategoryCL(CategoryType::PRODUCTION, CategorieCL::PRODUCTION_REQUEST);
        $columnsVisible = $currentUser->getVisibleColumns()['productionRequest'];

        $columns = [
            ...!$forExport
                ? [['name' => 'actions', 'alwaysVisible' => true, 'orderable' => false, 'class' => 'noVis']]
                : [],
            ['title' => FixedFieldEnum::number->value, 'name' => FixedFieldEnum::number->name],
            ['title' => FixedFieldEnum::createdAt->value, 'name' => FixedFieldEnum::createdAt->name],
            ['title' => FixedFieldEnum::createdBy->value, 'name' => FixedFieldEnum::createdBy->name],
            ['title' => FixedFieldEnum::treatedBy->value, 'name' => FixedFieldEnum::treatedBy->name],
            ['title' => FixedFieldEnum::type->value, 'name' => FixedFieldEnum::type->name],
            ['title' => FixedFieldEnum::status->value, 'name' => FixedFieldEnum::status->name],
            ['title' => FixedFieldEnum::expectedAt->value, 'name' => FixedFieldEnum::expectedAt->name],
            ['title' => FixedFieldEnum::dropLocation->value, 'name' => FixedFieldEnum::dropLocation->name],
            ['title' => FixedFieldEnum::lineCount->value, 'name' => FixedFieldEnum::lineCount->name],
            ['title' => FixedFieldEnum::manufacturingOrderNumber->value, 'name' => FixedFieldEnum::manufacturingOrderNumber->name],
            ['title' => FixedFieldEnum::productArticleCode->value, 'name' => FixedFieldEnum::productArticleCode->name],
            ['title' => FixedFieldEnum::quantity->value, 'name' => FixedFieldEnum::quantity->name],
            ['title' => FixedFieldEnum::emergency->value, 'name' => FixedFieldEnum::emergency->name],
            ['title' => FixedFieldEnum::projectNumber->value, 'name' => FixedFieldEnum::projectNumber->name],
            ['title' => FixedFieldEnum::comment->value, 'name' => FixedFieldEnum::comment->name],
        ];

        return $this->visibleColumnService->getArrayConfig($columns, $freeFields, $columnsVisible, $forExport);
    }

    public function getDataForDatatable(EntityManagerInterface $entityManager, Request $request) : array{
        $productionRepository = $entityManager->getRepository(ProductionRequest::class);

        $filtreSupRepository = $entityManager->getRepository(FiltreSup::class);
        $filters = $filtreSupRepository->getFieldAndValueByPageAndUser(FiltreSup::PAGE_PRODUCTION, $this->security->getUser());

        $queryResult = $productionRepository->findByParamsAndFilters(
            $request->request,
            $filters,
            $this->visibleColumnService,
            [
                'user' => $this->security->getUser(),
            ]
        );

        $shippingRequests = $queryResult['data'];

        $rows = [];
        foreach ($shippingRequests as $shipping) {
            $rows[] = $this->dataRowProduction($shipping);
        }

        return [
            'data' => $rows,
            'recordsFiltered' => $queryResult['count'],
            'recordsTotal' => $queryResult['total'],
        ];
    }

    public function dataRowProduction(ProductionRequest $productionRequest): array
    {
        $typeColor = $productionRequest->getType()->getColor();

        if (!isset($this->freeFieldsConfig)) {
            $this->freeFieldsConfig = $this->freeFieldService->getListFreeFieldConfig($this->entityManager, CategorieCL::PRODUCTION_REQUEST, CategoryType::PRODUCTION);
        }

        $url = $this->router->generate('production_request_show', [
            "id" => $productionRequest->getId()
        ]);

        $row = [
            "actions" => $this->templating->render('production_request/actions.html.twig', [
                'url' => $url,
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
            FixedFieldEnum::lineCount->name => $productionRequest->getLineCount(),
            FixedFieldEnum::manufacturingOrderNumber->name => $productionRequest->getManufacturingOrderNumber(),
            FixedFieldEnum::productArticleCode->name => $productionRequest->getProductArticleCode(),
            FixedFieldEnum::quantity->name => $productionRequest->getQuantity(),
            FixedFieldEnum::emergency->name => $productionRequest->getEmergency() ?: 'Non',
            FixedFieldEnum::projectNumber->name => $productionRequest->getProjectNumber(),
            FixedFieldEnum::comment->name => $productionRequest->getComment(),
        ];

        foreach ($this->freeFieldsConfig as $freeFieldId => $freeField) {
            $freeFieldName = $this->visibleColumnService->getFreeFieldName($freeFieldId);
            $freeFieldValue = $productionRequest->getFreeFieldValue($freeFieldId);
            $row[$freeFieldName] = $this->formatService->freeField($freeFieldValue, $freeField);
        }

        return $row;
    }

    public function updateProductionRequest(EntityManagerInterface $entityManager,
                                            ProductionRequest      $productionRequest,
                                            Utilisateur            $currentUser,
                                            InputBag               $data,
                                            FileBag                $fileBag): ProductionRequest {
        $typeRepository = $entityManager->getRepository(Type::class);
        $statusRepository = $entityManager->getRepository(Statut::class);
        $locationRepository = $entityManager->getRepository(Emplacement::class);
        $oldValues = [
            "status" => $productionRequest->getStatus(),
            "comment" => $productionRequest->getComment(),
            "dropLocation" => $productionRequest->getDropLocation(),
            "manufacturingOrderNumber" => $productionRequest->getManufacturingOrderNumber(),
            "emergency" => $productionRequest->getEmergency(),
            "expectedAt" => $productionRequest->getExpectedAt(),
            "projectNumber" => $productionRequest->getProjectNumber(),
            "productArticleCode" => $productionRequest->getProductArticleCode(),
            "quantity" => $productionRequest->getQuantity(),
            "lineCount" => $productionRequest->getLineCount(),
        ];

        if(!$productionRequest->getId()){
            $createdAt = new DateTime();
            $user = $this->userService->getUser();
            $number = $this->uniqueNumberService->create(
                $entityManager,
                ProductionRequest::NUMBER_PREFIX,
                ProductionRequest::class,
                UniqueNumberService::DATE_COUNTER_FORMAT_PRODUCTION_REQUEST,
                $createdAt,
            );

            $productionRequest
                ->setNumber($number)
                ->setCreatedAt($createdAt)
                ->setCreatedBy($user);

            if ($data->has(FixedFieldEnum::type->name)) {
                $type = $typeRepository->find($data->get(FixedFieldEnum::type->name));
                $productionRequest->setType($type);
            }
        }

        // array_key_exists() needed if creation fieldParams config != edit fieldParams config
        if ($data->has(FixedFieldEnum::status->name)) {
            $status = $statusRepository->find($data->get(FixedFieldEnum::status->name));
            $productionRequest->setStatus($status);
        }

        if ($data->has(FixedFieldEnum::dropLocation->name)) {
            $dropLocation = $data->get(FixedFieldEnum::dropLocation->name) ? $locationRepository->find($data->get(FixedFieldEnum::dropLocation->name)) : null;
            $productionRequest->setDropLocation($dropLocation);
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

        $this->freeFieldService->manageFreeFields($productionRequest, $data->all(), $entityManager);

        $attachments = $productionRequest->getAttachments()->toArray();
        foreach($attachments as $attachment) {
            /** @var Attachment $attachment */
            if($data->has('files') && !in_array($attachment->getId(), $data->all('files'))) {
                $this->attachmentService->removeAndDeleteAttachment($attachment, $productionRequest);
            }
        }

        $addedAttachments = $this->attachmentService->manageAttachments($entityManager, $productionRequest, $fileBag);

        $this->persistHistoryRecords(
            $entityManager,
            $productionRequest,
            $currentUser,
            new DateTime(),
            $oldValues,
            $addedAttachments
        );

        return $productionRequest;
    }

    private function persistHistoryRecords(EntityManagerInterface $entityManager,
                                           ProductionRequest      $productionRequest,
                                           Utilisateur            $currentUser,
                                           DateTime               $date,
                                           array                  $oldValues,
                                           array                  $addedAttachments): void {
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
                'label' => FixedFieldEnum::lineCount->value,
                'value' => $productionRequest->getLineCount(),
                'show' => ['fieldName' => FixedFieldEnum::lineCount->name],
            ],
        ];

        return $this->fixedFieldService->filterHeaderConfig($config, FixedFieldStandard::ENTITY_CODE_PRODUCTION);
    }

    public function buildCustomProductionHistoryMessage(ProductionRequest $productionRequest,
                                                        array             $oldValues): string {
        $oldDropLocation = $oldValues['dropLocation'] ?? null;
        $oldManufacturingOrderNumber = $oldValues['manufacturingOrderNumber'] ?? null;
        $oldEmergency = $oldValues['emergency'] ?? null;
        $oldExpectedAt = $oldValues['expectedAt'] ?? null;
        $oldProjectNumber = $oldValues['projectNumber'] ?? null;
        $oldProductArticleCode = $oldValues['productArticleCode'] ?? null;
        $oldQuantity = $oldValues['quantity'] ?? null;
        $oldLineCount = $oldValues['lineCount'] ?? null;

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
        $status = $productionRequest->getStatus();

        $hasRightToEdit = (
            $status
            && (
                ($status->isInProgress() && $this->userService->hasRightFunction(Menu::PRODUCTION, Action::EDIT_IN_PROGRESS_PRODUCTION_REQUEST))
                || ($status->isNotTreated() && $this->userService->hasRightFunction(Menu::PRODUCTION, Action::EDIT_TO_TREAT_PRODUCTION_REQUEST))
                || ($status->isTreated() && $this->userService->hasRightFunction(Menu::PRODUCTION, Action::EDIT_TREATED_PRODUCTION_REQUEST))
            )
        );

        if (!$hasRightToEdit) {
            throw new FormException("Vous n'avez pas les droits pour modifier cette demande de production");
        }
    }

    public function parseRequestForCard(ProductionRequest $productionRequest): array {
        $requestStatus = $productionRequest->getStatus()?->getCode();
        $requestState = $productionRequest->getStatus()?->getState();
        $requestType = $this->formatService->type($productionRequest->getType());

        $productionRequestExpectedAT = $productionRequest->getExpectedAt() ? $this->formatService->datetime($productionRequest->getExpectedAt()) : '';
        $estimatedFinishTimeLabel = $productionRequest->getExpectedAt() ? 'Date attendue' : '';

        $href = $this->router->generate('production_request_show', ['id' => $productionRequest->getId()]);

        $bodyTitle = `${$productionRequest->getManufacturingOrderNumber()} - ${$requestType}`;

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
}
