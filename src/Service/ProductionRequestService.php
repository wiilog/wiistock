<?php


namespace App\Service;
use App\Entity\Attachment;
use App\Entity\Emplacement;
use App\Entity\Fields\FixedFieldStandard;
use App\Entity\Fournisseur;
use App\Entity\ProductionRequest;
use App\Entity\Statut;
use App\Entity\Transporteur;
use App\Entity\Type;
use App\Entity\Urgence;
use App\Entity\Utilisateur;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\FileBag;
use Symfony\Contracts\Service\Attribute\Required;

class ProductionRequestService
{

    #[Required]
    public AttachmentService $attachmentService;

    #[Required]
    public FormatService $formatService;

    #[Required]
    public UniqueNumberService $uniqueNumberService;

    #[Required]
    public FixedFieldService $fixedFieldService;

    #[Required]
    public UserService $userService;

    public function updateProductionRequest(EntityManagerInterface $entityManager,
                                            ProductionRequest      $productionRequest,
                                            array                  $data,
                                            FileBag                $fileBag,
                                            bool                   $isCreation = false): ProductionRequest {
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

        if (array_key_exists(FixedFieldStandard::FIELD_CODE_EXPECTED_DATE_AND_TIME, $data)) {
            $productionRequest->setExpectedAt($this->formatService->parseDatetime($data[FixedFieldStandard::FIELD_CODE_EXPECTED_DATE_AND_TIME]));
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
                'show' => ['fieldName' => FixedFieldStandard::FIELD_CODE_EXPECTED_DATE_AND_TIME],
            ],
            [
                'label' => 'Numéro de projet',
                'value' => $productionRequest->getProjectNumber(),
                'show' => ['fieldName' => FixedFieldStandard::FIELD_CODE_PROJECT_NUMBER],
            ],
            [
                'label' => 'Code produit / article',
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
