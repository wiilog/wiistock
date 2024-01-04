<?php

namespace App\Service;

use App\Entity\CategorieCL;
use App\Entity\CategoryType;
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

    private ?array $freeFieldsConfig = null;

    public function getVisibleColumnsConfig(EntityManagerInterface $entityManager, Utilisateur $currentUser): array {
        $champLibreRepository = $entityManager->getRepository(FreeField::class);

        $freeFields = $champLibreRepository->findByCategoryTypeAndCategoryCL(CategoryType::PRODUCTION, CategorieCL::PRODUCTION_REQUEST);
        $columnsVisible = $currentUser->getVisibleColumns()['productionRequest'];

        $columns = [
            ['name' => 'actions', 'alwaysVisible' => true, 'orderable' => false, 'class' => 'noVis'],
            ['title' => 'Numéro de demande', 'name' => 'number'],
            ['title' => 'Date de création', 'name' => 'createdAt'],
            ['title' => 'Traité par', 'name' => 'treatedBy'],
            ['title' => 'Type', 'name' => 'type'],
            ['title' => 'Statut', 'name' => 'status'],
            ['title' => FixedFieldStandard::FIELD_LABEL_EXPECTED_AT, 'name' => FixedFieldStandard::FIELD_CODE_EXPECTED_AT],
            ['title' => FixedFieldStandard::FIELD_LABEL_LOCATION_DROP, 'name' => FixedFieldStandard::FIELD_CODE_LOCATION_DROP],
            ['title' => FixedFieldStandard::FIELD_LABEL_LINE_COUNT, 'name' => FixedFieldStandard::FIELD_CODE_LINE_COUNT],
            ['title' => FixedFieldStandard::FIELD_LABEL_MANUFACTURING_ORDER_NUMBER, 'name' => FixedFieldStandard::FIELD_CODE_MANUFACTURING_ORDER_NUMBER],
            ['title' => FixedFieldStandard::FIELD_LABEL_PRODUCT_ARTICLE_CODE, 'name' => FixedFieldStandard::FIELD_CODE_PRODUCT_ARTICLE_CODE],
            ['title' => FixedFieldStandard::FIELD_LABEL_QUANTITY, 'name' => FixedFieldStandard::FIELD_CODE_QUANTITY],
            ['title' => FixedFieldStandard::FIELD_LABEL_EMERGENCY, 'name' => FixedFieldStandard::FIELD_CODE_EMERGENCY],
            ['title' => FixedFieldStandard::FIELD_LABEL_PROJECT_NUMBER, 'name' => FixedFieldStandard::FIELD_CODE_PROJECT_NUMBER],
            ['title' => FixedFieldStandard::FIELD_LABEL_COMMENTAIRE, 'name' => FixedFieldStandard::FIELD_CODE_COMMENTAIRE],
        ];

        return $this->visibleColumnService->getArrayConfig($columns, $freeFields, $columnsVisible);
    }

    public function getDataForDatatable(EntityManagerInterface $entityManager, Request $request) : array{
        $productionRepository = $entityManager->getRepository(ProductionRequest::class);

        /*
        todo WIIS-10759
        $filtreSupRepository = $entityManager->getRepository(FiltreSup::class);
        $filters = $filtreSupRepository->getFieldAndValueByPageAndUser(FiltreSup::PAGE_SHIPPING, $this->security->getUser());
        */

        $queryResult = $productionRepository->findByParamsAndFilters(
            $request->request,
            /* todo : todo WIIS-10759 $filters */ [],
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
            "number" => $productionRequest->getNumber() ?? '',
            "createdAt" => $formatService->datetime($productionRequest->getCreatedAt()),
            "treatedBy" => $this->formatService->user($productionRequest->getTreatedBy()),
            "type" => "
                <div class='d-flex align-items-center'>
                    <span class='dt-type-color mr-2' style='background-color: $typeColor;'></span>
                    {$this->formatService->type($productionRequest->getType())}
                </div>
            ",
            "status" => $formatService->status($productionRequest->getStatus()),
            FixedFieldStandard::FIELD_CODE_EXPECTED_AT => $formatService->datetime($productionRequest->getExpectedAt()),
            FixedFieldStandard::FIELD_CODE_LOCATION_DROP => $formatService->location($productionRequest->getDropLocation()),
            FixedFieldStandard::FIELD_CODE_LINE_COUNT => $productionRequest->getLineCount(),
            FixedFieldStandard::FIELD_CODE_MANUFACTURING_ORDER_NUMBER => $productionRequest->getManufacturingOrderNumber(),
            FixedFieldStandard::FIELD_CODE_PRODUCT_ARTICLE_CODE => $productionRequest->getProductArticleCode(),
            FixedFieldStandard::FIELD_CODE_QUANTITY => $productionRequest->getQuantity(),
            FixedFieldStandard::FIELD_CODE_EMERGENCY => $productionRequest->getEmergency() ?: 'Non',
            FixedFieldStandard::FIELD_CODE_PROJECT_NUMBER => $productionRequest->getProjectNumber(),
            FixedFieldStandard::FIELD_CODE_COMMENTAIRE => $productionRequest->getComment(),
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
        }

        // array_key_exists() needed if creation fieldParams config != edit fieldParams config

        if (array_key_exists(FixedFieldStandard::FIELD_CODE_TYPE_PRODUCTION, $data)) {
            $type = $typeRepository->find($data[FixedFieldStandard::FIELD_CODE_TYPE_PRODUCTION]);
            $productionRequest->setType($type);
        }

        if (array_key_exists(FixedFieldStandard::FIELD_CODE_STATUS_PRODUCTION, $data)) {
            $status = $statusRepository->find($data[FixedFieldStandard::FIELD_CODE_STATUS_PRODUCTION]);
            $productionRequest->setStatus($status);
        }

        if (array_key_exists(FixedFieldStandard::FIELD_CODE_LOCATION_DROP, $data)) {
            $dropLocation = $locationRepository->find($data[FixedFieldStandard::FIELD_CODE_LOCATION_DROP]);
            $productionRequest->setDropLocation($dropLocation);
        }

        if (array_key_exists(FixedFieldStandard::FIELD_CODE_MANUFACTURING_ORDER_NUMBER, $data)) {
            $productionRequest->setManufacturingOrderNumber($data[FixedFieldStandard::FIELD_CODE_MANUFACTURING_ORDER_NUMBER]);
        }

        if (array_key_exists(FixedFieldStandard::FIELD_CODE_EMERGENCY, $data)) {
            $productionRequest->setEmergency($data[FixedFieldStandard::FIELD_CODE_EMERGENCY]);
        }

        if (array_key_exists(FixedFieldStandard::FIELD_CODE_EXPECTED_AT, $data)) {
            $productionRequest->setExpectedAt($this->formatService->parseDatetime($data[FixedFieldStandard::FIELD_CODE_EXPECTED_AT]));
        }

        if (array_key_exists(FixedFieldStandard::FIELD_CODE_PROJECT_NUMBER, $data)) {
            $productionRequest->setProjectNumber($data[FixedFieldStandard::FIELD_CODE_PROJECT_NUMBER]);
        }

        if (array_key_exists(FixedFieldStandard::FIELD_CODE_PRODUCT_ARTICLE_CODE, $data)) {
            $productionRequest->setProductArticleCode($data[FixedFieldStandard::FIELD_CODE_PRODUCT_ARTICLE_CODE]);
        }

        if (array_key_exists(FixedFieldStandard::FIELD_CODE_QUANTITY, $data)) {
            $productionRequest->setQuantity($data[FixedFieldStandard::FIELD_CODE_QUANTITY]);
        }

        if (array_key_exists(FixedFieldStandard::FIELD_CODE_LINE_COUNT, $data)) {
            $productionRequest->setLineCount($data[FixedFieldStandard::FIELD_CODE_LINE_COUNT]);
        }

        if (array_key_exists(FixedFieldStandard::FIELD_CODE_COMMENTAIRE, $data)) {
            $productionRequest->setComment($data[FixedFieldStandard::FIELD_CODE_COMMENTAIRE]);
        }

        $attachments = $productionRequest->getAttachments()->toArray();
        foreach($attachments as $attachment) {
            /** @var Attachment $attachment */
            if(!in_array($attachment->getId(), $fileBag->all())) {
                $this->attachmentService->removeAndDeleteAttachment($attachment, $productionRequest);
            }
        }

        $this->attachmentService->manageAttachments($entityManager, $productionRequest, $fileBag);


        return $productionRequest;
    }

    public function createHeaderDetailsConfig(ProductionRequest $productionRequest): array {
        $config = [
            [
                'label' => 'Numéro OF',
                'value' => $productionRequest->getManufacturingOrderNumber(),
                'show' => ['fieldName' => FixedFieldStandard::FIELD_CODE_MANUFACTURING_ORDER_NUMBER],
            ],
            [
                'label' => 'Date de création',
                'value' =>  $this->formatService->datetime($productionRequest->getCreatedAt()),
            ],
            [
                'label' => 'Date attendue',
                'value' => $this->formatService->datetime($productionRequest->getExpectedAt()),
                'show' => ['fieldName' => FixedFieldStandard::FIELD_CODE_EXPECTED_AT],
            ],
            [
                'label' => 'Numéro de projet',
                'value' => $productionRequest->getProjectNumber(),
                'show' => ['fieldName' => FixedFieldStandard::FIELD_CODE_PROJECT_NUMBER],
            ],
            [
                'label' => 'Code produit/article',
                'value' => $productionRequest->getProductArticleCode(),
                'show' => ['fieldName' => FixedFieldStandard::FIELD_CODE_PRODUCT_ARTICLE_CODE],
            ],
            [
                'label' => 'Quantité',
                'value' => $productionRequest->getQuantity(),
                'show' => ['fieldName' => FixedFieldStandard::FIELD_CODE_QUANTITY],
            ],
            [
                'label' => 'Emplacement de dépose',
                'value' => $this->formatService->location($productionRequest->getDropLocation()),
                'show' => ['fieldName' => FixedFieldStandard::FIELD_CODE_LOCATION_DROP],

            ],
            [
                'label' => 'Nombre de lignes',
                'value' => $productionRequest->getLineCount(),
                'show' => ['fieldName' => FixedFieldStandard::FIELD_CODE_LINE_COUNT],
            ],
        ];

        return $this->fixedFieldService->filterHeaderConfig($config, FixedFieldStandard::ENTITY_CODE_PRODUCTION);
    }
}
