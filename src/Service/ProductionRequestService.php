<?php


namespace App\Service;

use App\Entity\Fields\FixedFieldStandard;
use App\Entity\ProductionRequest;
use Symfony\Contracts\Service\Attribute\Required;

class ProductionRequestService
{

    #[Required]
    public FormatService $formatService;

    #[Required]
    public FixedFieldService $fixedFieldService;

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
