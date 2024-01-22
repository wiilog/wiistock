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
                                            InputBag               $data,
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

            if ($data->has(FixedFieldEnum::type->name)) {
                $type = $typeRepository->find($data->get(FixedFieldEnum::type->name));
                $productionRequest->setType($type);
            }

            if ($data->has(FixedFieldEnum::status->name)) {
                $status = $statusRepository->find($data->get(FixedFieldEnum::status->name));
                $productionRequest->setStatus($status);
            }
        }

        // array_key_exists() needed if creation fieldParams config != edit fieldParams config

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

        $attachments = $productionRequest->getAttachments()->toArray();
        foreach($attachments as $attachment) {
            /** @var Attachment $attachment */
            if($data->has('files') && !in_array($attachment->getId(), $data->all('files'))) {
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
                                        InputBag               $data,
                                        FileBag                $fileBag) : string{
        $locationRepository = $entityManager->getRepository(Emplacement::class);
        $user = $this->userService->getUser();

        $message = "<br>";
        if ($data->has(FixedFieldEnum::dropLocation->name)
            && $productionRequest->getDropLocation()?->getId() !== $data->getInt(FixedFieldEnum::dropLocation->name) ? intval($data->get(FixedFieldEnum::dropLocation->name)) : null) {
            $dropLocation = $locationRepository->find($data->get(FixedFieldEnum::dropLocation->name));
            $message .= "<strong>".FixedFieldEnum::dropLocation->value."</strong> : {$dropLocation->getLabel()}.<br>";
        }

        if ($data->has(FixedFieldEnum::manufacturingOrderNumber->name)
            && $productionRequest->getManufacturingOrderNumber() !== $data->get(FixedFieldEnum::manufacturingOrderNumber->name)) {
            $message .= "<strong>".FixedFieldEnum::manufacturingOrderNumber->value."</strong> : {$data->get(FixedFieldEnum::manufacturingOrderNumber->name)} <br>";
        }

        if ($data->has(FixedFieldEnum::emergency->name)
            && $productionRequest->getEmergency() !== $data->get(FixedFieldEnum::emergency->name)) {
            $message .= "<strong>".FixedFieldEnum::emergency->value."</strong> : {$data->get(FixedFieldEnum::emergency->name)}<br>";
        }

        if ($data->has(FixedFieldEnum::expectedAt->name)
            && $productionRequest->getExpectedAt()?->format('Y-m-d') !== $data->get(FixedFieldEnum::expectedAt->name)) {
            $message .= "<strong>".FixedFieldEnum::expectedAt->value."</strong> : {$data->get(FixedFieldEnum::expectedAt->name)}<br>";
        }

        if ($data->has(FixedFieldEnum::projectNumber->name)
            && $productionRequest->getProjectNumber() !== $data->get(FixedFieldEnum::projectNumber->name)) {
            $message .= "<strong>".FixedFieldEnum::projectNumber->value."</strong> : {$data->get(FixedFieldEnum::projectNumber->name)}<br>";
        }

        if ($data->has(FixedFieldEnum::productArticleCode->name)
            && $productionRequest->getProductArticleCode() !== $data->get(FixedFieldEnum::productArticleCode->name)) {
            $message .= "<strong>".FixedFieldEnum::productArticleCode->value."</strong> : {$data->get(FixedFieldEnum::productArticleCode->name)}<br>";
        }

        if ($data->has(FixedFieldEnum::quantity->name)
            && intval($productionRequest->getQuantity()) !== intval($data->get(FixedFieldEnum::quantity->name))) {
            $message .= "<strong>".FixedFieldEnum::quantity->value."</strong> : {$data->get(FixedFieldEnum::quantity->name)}<br>";
        }

        if ($data->has(FixedFieldEnum::lineCount->name)
            && intval($productionRequest->getLineCount()) !== intval($data->get(FixedFieldEnum::lineCount->name))) {
            $message .= "<strong>".FixedFieldEnum::lineCount->value."</strong> : {$data->get(FixedFieldEnum::lineCount->name)}<br>";
        }

        if ($data->has(FixedFieldEnum::comment->name)
            && $productionRequest->getComment() !== $data->get(FixedFieldEnum::comment->name)) {
            $productionRequestHistoryRecord = $this->operationHistoryService->persistProductionHistory(
                $entityManager,
                $productionRequest,
                OperationHistoryService::TYPE_ADD_COMMENT,
                [
                    "user" => $user,
                    "comment" => $data->get(FixedFieldEnum::comment->name),
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
