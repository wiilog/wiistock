<?php

namespace App\Service;

use App\Entity\CategorieCL;
use App\Entity\CategoryType;
use App\Entity\Fields\FixedFieldEnum;
use App\Entity\FiltreSup;
use App\Entity\FreeField;
use App\Entity\ProductionRequest;
use App\Entity\Utilisateur;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
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
        $formatService = $this->formatService;
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
            FixedFieldEnum::createdAt->name => $formatService->datetime($productionRequest->getCreatedAt()),
            FixedFieldEnum::createdBy->name => $formatService->user($productionRequest->getCreatedBy()),
            FixedFieldEnum::treatedBy->name => $this->formatService->user($productionRequest->getTreatedBy()),
            FixedFieldEnum::type->name => "
                <div class='d-flex align-items-center'>
                    <span class='dt-type-color mr-2' style='background-color: $typeColor;'></span>
                    {$this->formatService->type($productionRequest->getType())}
                </div>
            ",
            FixedFieldEnum::status->name => $formatService->status($productionRequest->getStatus()),
            FixedFieldEnum::expectedAt->name => $formatService->datetime($productionRequest->getExpectedAt()),
            FixedFieldEnum::dropLocation->name => $formatService->location($productionRequest->getDropLocation()),
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
                                            array                  $data,
                                            FileBag                $fileBag): ProductionRequest {
        $typeRepository = $entityManager->getRepository(Type::class);
        $statusRepository = $entityManager->getRepository(Statut::class);
        $locationRepository = $entityManager->getRepository(Emplacement::class);

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

            if (array_key_exists(FixedFieldEnum::type->name, $data)) {
                $type = $typeRepository->find($data[FixedFieldEnum::type->name]);
                $productionRequest->setType($type);
            }

            if (array_key_exists(FixedFieldEnum::status->name, $data)) {
                $status = $statusRepository->find($data[FixedFieldEnum::status->name]);
                $productionRequest->setStatus($status);
            }
        }

        // array_key_exists() needed if creation fieldParams config != edit fieldParams config

        if (array_key_exists(FixedFieldEnum::dropLocation->name, $data)) {
            $dropLocation = $data[FixedFieldEnum::dropLocation->name] ? $locationRepository->find($data[FixedFieldEnum::dropLocation->name]) : null;
            $productionRequest->setDropLocation($dropLocation);
        }

        if (array_key_exists(FixedFieldEnum::manufacturingOrderNumber->name, $data)) {
            $productionRequest->setManufacturingOrderNumber($data[FixedFieldEnum::manufacturingOrderNumber->name]);
        }

        if (array_key_exists(FixedFieldEnum::emergency->name, $data)) {
            $productionRequest->setEmergency($data[FixedFieldEnum::emergency->name]);
        }

        if (array_key_exists(FixedFieldEnum::expectedAt->name, $data)) {
            $productionRequest->setExpectedAt($this->formatService->parseDatetime($data[FixedFieldEnum::expectedAt->name]));
        }

        if (array_key_exists(FixedFieldEnum::projectNumber->name, $data)) {
            $productionRequest->setProjectNumber($data[FixedFieldEnum::projectNumber->name]);
        }

        if (array_key_exists(FixedFieldEnum::productArticleCode->name, $data)) {
            $productionRequest->setProductArticleCode($data[FixedFieldEnum::productArticleCode->name]);
        }

        if (array_key_exists(FixedFieldEnum::quantity->name, $data)) {
            $productionRequest->setQuantity($data[FixedFieldEnum::quantity->name]);
        }

        if (array_key_exists(FixedFieldEnum::lineCount->name, $data)) {
            $productionRequest->setLineCount($data[FixedFieldEnum::lineCount->name]);
        }

        if (array_key_exists(FixedFieldEnum::comment->name, $data)) {
            $productionRequest->setComment($data[FixedFieldEnum::comment->name]);
        }

        $attachments = $productionRequest->getAttachments()->toArray();
        foreach($attachments as $attachment) {
            /** @var Attachment $attachment */
            if(isset($data['files']) && !in_array($attachment->getId(), $data['files'])) {
                $this->attachmentService->removeAndDeleteAttachment($attachment, $productionRequest);
            }
        }

        if(!$productionRequest->getId()) {
            $this->attachmentService->manageAttachments($entityManager, $productionRequest, $fileBag);
        }

        return $productionRequest;
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

    public function buildMessageForEdit(EntityManagerInterface $entityManager,
                                        ProductionRequest      $productionRequest,
                                        array                  $data,
                                        FileBag                $fileBag) : string{
        $locationRepository = $entityManager->getRepository(Emplacement::class);
        $user = $this->userService->getUser();

        $message = "<br>";
        if (array_key_exists(FixedFieldEnum::dropLocation->name, $data)
            && $productionRequest->getDropLocation()
            && $productionRequest->getDropLocation()->getId() !== intval($data[FixedFieldEnum::dropLocation->name])) {
            $dropLocation = $locationRepository->find($data[FixedFieldEnum::dropLocation->name]);
            $message .= "<strong>Emplacement dépose</strong> : {$dropLocation->getLabel()}.<br>";
        }

        if (array_key_exists(FixedFieldEnum::manufacturingOrderNumber->name, $data)
            && $productionRequest->getManufacturingOrderNumber() !== $data[FixedFieldEnum::manufacturingOrderNumber->name]) {
            $message .= "<strong>Numéro d'OF</strong> : {$data[FixedFieldEnum::manufacturingOrderNumber->name]} <br>";
        }

        if (array_key_exists(FixedFieldEnum::emergency->name, $data)
            && $productionRequest->getEmergency() !== $data[FixedFieldEnum::emergency->name]) {
            $message .= "<strong>Urgence</strong> : {$data[FixedFieldEnum::emergency->name]}<br>";
        }

        if (array_key_exists(FixedFieldEnum::expectedAt->name, $data)
            && $productionRequest->getExpectedAt() !== $data[FixedFieldEnum::expectedAt->name]) {
            $message .= "<strong>Date attendue</strong> : {$data[FixedFieldEnum::expectedAt->name]}<br>";
        }

        if (array_key_exists(FixedFieldEnum::projectNumber->name, $data)
            && $productionRequest->getProjectNumber() !== $data[FixedFieldEnum::projectNumber->name]) {
            $message .= "<strong>Numéro de projet</strong> : {$data[FixedFieldEnum::projectNumber->name]}<br>";
        }

        if (array_key_exists(FixedFieldEnum::productArticleCode->name, $data)
            && $productionRequest->getProductArticleCode() !== $data[FixedFieldEnum::productArticleCode->name]) {
            $message .= "<strong>Code produit/article</strong> : {$data[FixedFieldEnum::productArticleCode->name]}<br>";
        }

        if (array_key_exists(FixedFieldEnum::quantity->name, $data)
            && $productionRequest->getQuantity() !== null
            && $productionRequest->getQuantity() !== intval($data[FixedFieldEnum::quantity->name])) {
            $message .= "<strong>Quantité</strong> : {$data[FixedFieldEnum::quantity->name]}<br>";
        }

        if (array_key_exists(FixedFieldEnum::lineCount->name, $data)
            && $productionRequest->getLineCount() !== null
            && $productionRequest->getLineCount() !== intval($data[FixedFieldEnum::lineCount->name])) {
            $message .= "<strong>Nombre de lignes</strong> : {$data[FixedFieldEnum::lineCount->name]}<br>";
        }

        if (array_key_exists(FixedFieldEnum::comment->name, $data)
            && $productionRequest->getComment() !== $data[FixedFieldEnum::comment->name]) {
            $productionRequestHistoryRecord = $this->operationHistoryService->persistProductionHistory(
                $entityManager,
                $productionRequest,
                OperationHistoryService::TYPE_ADD_COMMENT,
                [
                    "user" => $user,
                    "comment" => $data[FixedFieldEnum::comment->name],
                ]
            );
            $entityManager->persist($productionRequestHistoryRecord);
        }

        $addedAttachments = $this->attachmentService->manageAttachments($entityManager, $productionRequest, $fileBag);
        if (!empty($addedAttachments)) {
            $productionRequestHistoryRecord = $this->operationHistoryService->persistProductionHistory(
                $entityManager,
                $productionRequest,
                OperationHistoryService::TYPE_ADD_ATTACHMENT,
                [
                    "user" => $user,
                    "attachments" => $addedAttachments,
                ]
            );
            $entityManager->persist($productionRequestHistoryRecord);
        }

        return $message;
    }

    public function productionRequestPutLine($output, array $productionRequest, array $freeFieldsConfig, array $freeFieldsById): void {
        $freeFieldValues = $freeFieldsById[$productionRequest['id']];
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
            strip_tags(FixedFieldEnum::comment->name),
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
}
